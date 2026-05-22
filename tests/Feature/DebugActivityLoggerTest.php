<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests\Feature;

use LaravelAgentDebugger\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use LaravelAgentDebugger\DebugLoggerManager;
use LaravelAgentDebugger\Middleware\DebugActivityLogger;
use Illuminate\Support\Facades\File;

class DebugActivityLoggerTest extends TestCase
{
    protected DebugLoggerManager $manager;
    protected DebugActivityLogger $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manager = $this->app->make(DebugLoggerManager::class);
        $this->middleware = new DebugActivityLogger($this->manager);

        config(['agent-debugger.enabled' => true]);
        config(['agent-debugger.log_style' => 'single']);
    }

    /** @test */
    public function test_it_successfully_compiles_request_headers_and_payloads_to_log_file()
    {
        $request = Request::create('/submit-form', 'POST', [
            'username' => 'johndoe',
            'password' => 'secret123', // should be redacted
            'secret_token' => 'secure_value_abc', // should be redacted
            'items' => [1, 2, 3, 4, 5, 6], // should be truncated
        ]);

        // Assign mock session parameters
        $request->setLaravelSession(Session::driver('array'));
        $request->session()->put('active_step', 4);

        $response = $this->middleware->handle($request, function () {
            $resp = new Response('Form Submitted');
            $resp->headers->set('Content-Type', 'text/plain');
            return $resp;
        });

        // Trigger termination to write logs
        $this->middleware->terminate($request, $response);

        $logPath = $this->tempLogPath . '/agent_debug.log';
        $this->assertFileExists($logPath);

        $logContents = File::get($logPath);

        // Assert basic Request values
        $this->assertStringContainsString('REQUEST: POST http://localhost/submit-form', $logContents);
        $this->assertStringContainsString('Response Status: 200', $logContents);

        // Assert intelligent recursive redactions
        $this->assertStringContainsString('"password": "[REDACTED]"', $logContents);
        $this->assertStringContainsString('"secret_token": "[REDACTED]"', $logContents);

        // Assert balanced payload truncations
        $this->assertStringContainsString('truncated 4 more items', $logContents);

        // Assert session states
        $this->assertStringContainsString('active_step => 4', $logContents);
    }

    /** @test */
    public function test_it_captures_db_queries_and_detects_n_plus_one_loops()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite driver is not available in the testing environment.');
        }

        // Execute database actions to seed logs
        DB::statement('CREATE TABLE dummy_items (id INTEGER PRIMARY KEY)');

        $request = Request::create('/db-queries', 'GET');
        $request->setLaravelSession(Session::driver('array'));

        $this->middleware->handle($request, function () {
            // Trigger 6 repeating queries
            for ($i = 0; $i < 6; $i++) {
                DB::select('select * from dummy_items where id = ?', [1]);
            }
            return new Response('Database Done');
        });

        $this->middleware->terminate($request, new Response('Database Done'));

        $logPath = $this->tempLogPath . '/agent_debug.log';
        $logContents = File::get($logPath);

        // Assert queries recorded
        $this->assertStringContainsString('select * from dummy_items where id = 1', $logContents);

        // Assert repeating N+1 Query Warning Loop detects matches
        $this->assertStringContainsString('⚠️ WARNING: N+1 Query Detected!', $logContents);
        $this->assertStringContainsString('executed 6 times', $logContents);
    }

    /** @test */
    public function test_it_captures_and_formats_thrown_exceptions_to_logs()
    {
        $request = Request::create('/crash-page', 'GET');
        $request->setLaravelSession(Session::driver('array'));

        try {
            $this->middleware->handle($request, function () {
                throw new \RuntimeException('Database Connection Dropped Silently.');
            });
        } catch (\RuntimeException $e) {
            // Suppress bubbling to write logs
        }

        $this->middleware->terminate($request, new Response('Crashed', 500));

        $logPath = $this->tempLogPath . '/agent_debug.log';
        $logContents = File::get($logPath);

        // Assert throw details captured
        $this->assertStringContainsString('[UNHANDLED APPLICATION CRASH]', $logContents);
        $this->assertStringContainsString('Class: RuntimeException', $logContents);
        $this->assertStringContainsString('Message: Database Connection Dropped Silently.', $logContents);
    }

    /** @test */
    public function test_it_can_profile_performance_spans_via_helper()
    {
        $request = Request::create('/heavy-operation', 'GET');
        $request->setLaravelSession(Session::driver('array'));

        $this->middleware->handle($request, function () {
            // Measure execution via the premium debug_span helper
            debug_span('Stripe Checkout API Call', function () {
                usleep(5000); // mock heavy external load
            });
            return new Response('Spans Recorded');
        });

        $this->middleware->terminate($request, new Response('Spans Recorded'));

        $logPath = $this->tempLogPath . '/agent_debug.log';
        $logContents = File::get($logPath);

        // Assert spans are compiled inside the log
        $this->assertStringContainsString('- PERFORMANCE SPANS:', $logContents);
        $this->assertStringContainsString('Stripe Checkout API Call', $logContents);
    }
}
