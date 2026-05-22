<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests\Feature;

use LaravelAgentDebugger\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use LaravelAgentDebugger\DebugLoggerManager;
use LaravelAgentDebugger\Listeners\CacheProfiler;
use LaravelAgentDebugger\Listeners\EloquentProfiler;
use LaravelAgentDebugger\Listeners\EventJobProfiler;
use LaravelAgentDebugger\Listeners\HttpClientProfiler;
use LaravelAgentDebugger\Listeners\SessionStateProfiler;
use LaravelAgentDebugger\Listeners\AuthorizationProfiler;
use LaravelAgentDebugger\Listeners\ViewProfiler;
use LaravelAgentDebugger\Listeners\EnvironmentTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Define helper model for eloquent profiling tests
class TelemetryTestModel extends Model
{
    protected $table = 'telemetry_test_models';
    protected $guarded = [];
}

class ProfilerTelemetryTest extends TestCase
{
    protected DebugLoggerManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(DebugLoggerManager::class);
        config(['agent-debugger.enabled' => true]);
    }

    /** @test */
    public function test_cache_profiler_captures_cache_actions()
    {
        $profiler = new CacheProfiler($this->manager);
        $profiler->subscribe();

        Cache::put('agent-test-key', 'hello-world', 10);
        Cache::get('agent-test-key');
        Cache::get('agent-missing-key');
        Cache::forget('agent-test-key');

        $actions = $this->manager->getCacheActions();

        $this->assertCount(4, $actions);

        $this->assertEquals('WRITE', $actions[0]['type']);
        $this->assertEquals('agent-test-key', $actions[0]['key']);
        $this->assertEquals(10, $actions[0]['ttl']);

        $this->assertEquals('HIT', $actions[1]['type']);
        $this->assertEquals('agent-test-key', $actions[1]['key']);

        $this->assertEquals('MISS', $actions[2]['type']);
        $this->assertEquals('agent-missing-key', $actions[2]['key']);

        $this->assertEquals('FORGET', $actions[3]['type']);
        $this->assertEquals('agent-test-key', $actions[3]['key']);
    }

    /** @test */
    public function test_eloquent_profiler_captures_model_lifecycle_events()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite driver is not available.');
        }

        Schema::create('telemetry_test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $profiler = new EloquentProfiler($this->manager);
        $profiler->subscribe();

        $model = TelemetryTestModel::create(['name' => 'Test Record']);
        $model->update(['name' => 'Updated Name']);
        $model->delete();

        $events = $this->manager->getEloquentEvents();

        // Model events captured: creating, created, updating, updated, deleting, deleted
        $this->assertNotEmpty($events);

        $modelClasses = array_column($events, 'model');
        $this->assertContains(TelemetryTestModel::class, $modelClasses);

        $actionEvents = array_column($events, 'event');
        $this->assertContains('created', $actionEvents);
        $this->assertContains('updated', $actionEvents);
        $this->assertContains('deleted', $actionEvents);
    }

    /** @test */
    public function test_event_job_profiler_captures_dispatched_events_and_jobs()
    {
        $profiler = new EventJobProfiler($this->manager);
        $profiler->subscribe();

        // 1. Dispatch custom event
        event('custom.test.event', ['key' => 'value']);

        // 2. Dispatch queued job
        $job = new class {
            public string $testParam = 'job-value';
        };
        event(new \Illuminate\Queue\Events\JobQueued('sync', 'default', 1, $job, '{}', null));

        $events = $this->manager->getEvents();
        $jobs = $this->manager->getJobs();

        // Verify Event
        $this->assertNotEmpty($events);
        $eventNames = array_column($events, 'name');
        $this->assertContains('custom.test.event', $eventNames);

        // Verify Job
        $this->assertNotEmpty($jobs);
        $this->assertStringContainsString('class@anonymous', $jobs[0]['name']);
        $this->assertEquals('sync:default', $jobs[0]['queue']);
        $this->assertEquals('job-value', $jobs[0]['payload']['testParam']);
    }

    /** @test */
    public function test_http_client_profiler_captures_outgoing_http_calls()
    {
        $profiler = new HttpClientProfiler($this->manager);
        $profiler->subscribe();

        Http::fake([
            'https://api.external.com/users' => Http::response(['id' => 1], 200),
            'https://api.external.com/fail' => Http::response(null, 500),
        ]);

        Http::get('https://api.external.com/users');
        Http::post('https://api.external.com/fail');

        $calls = $this->manager->getHttpCalls();

        $this->assertCount(2, $calls);

        $this->assertEquals('GET', $calls[0]['method']);
        $this->assertEquals('https://api.external.com/users', $calls[0]['url']);
        $this->assertEquals(200, $calls[0]['status']);

        $this->assertEquals('POST', $calls[1]['method']);
        $this->assertEquals('https://api.external.com/fail', $calls[1]['url']);
        $this->assertEquals(500, $calls[1]['status']);
    }

    /** @test */
    public function test_session_state_profiler_tracks_developer_keys_and_csrf_state()
    {
        $profiler = new SessionStateProfiler($this->manager);

        $request = \Illuminate\Http\Request::create('/form-submit', 'POST', [
            '_token' => 'match-token',
        ]);
        $request->setLaravelSession(Session::driver('array'));
        $request->session()->put('_token', 'match-token');
        $request->session()->put('developer_key', 'dev-value');
        $request->session()->put('_flash', ['old' => [], 'new' => []]);

        $profiler->profile($request);

        $sessionState = $this->manager->getSessionState();
        $csrfState = $this->manager->getCsrfState();

        // Should filter out internal keys like _token and _flash, but keep developer_key
        $this->assertArrayHasKey('developer_key', $sessionState);
        $this->assertEquals('dev-value', $sessionState['developer_key']);
        $this->assertArrayNotHasKey('_token', $sessionState);
        $this->assertArrayNotHasKey('_flash', $sessionState);

        // Verify CSRF state matched successfully
        $this->assertTrue($csrfState['checked']);
        $this->assertTrue($csrfState['passed']);
        $this->assertEquals('match-token', $csrfState['request_token']);
        $this->assertEquals('match-token', $csrfState['session_token']);
    }

    /** @test */
    public function test_authorization_profiler_tracks_gate_evaluations()
    {
        $profiler = new AuthorizationProfiler($this->manager);
        $profiler->subscribe();

        $user = new \Illuminate\Foundation\Auth\User();
        $user->forceFill(['id' => 1]);
        $this->actingAs($user);

        Gate::define('test-auth-ability', function ($user, $outcome) {
            return $outcome === 'allow';
        });

        // Simulate user gate check
        Gate::allows('test-auth-ability', ['allow']);
        Gate::allows('test-auth-ability', ['deny']);

        $gates = $this->manager->getGates();

        $this->assertCount(2, $gates);

        $this->assertEquals('test-auth-ability', $gates[0]['ability']);
        $this->assertEquals('ALLOWED', $gates[0]['result']);

        $this->assertEquals('test-auth-ability', $gates[1]['ability']);
        $this->assertEquals('DENIED', $gates[1]['result']);
    }

    /** @test */
    public function test_view_profiler_tracks_view_composition()
    {
        $profiler = new ViewProfiler($this->manager);
        $profiler->subscribe();

        // Create a temporary view path for testing
        $viewPath = $this->tempLogPath . '/views';
        if (!file_exists($viewPath)) {
            mkdir($viewPath, 0755, true);
        }
        file_put_contents($viewPath . '/dummy-view.blade.php', 'Hello World');
        View::addLocation($viewPath);

        // Fake view compositions
        View::composer('dummy-view', function () {});
        view('dummy-view')->render();

        $views = $this->manager->getViews();

        $this->assertContains('dummy-view', $views);
    }

    /** @test */
    public function test_environment_tracker_detects_config_drifts()
    {
        // Suppress target cache key
        Cache::forget('agent_debugger_env_values');

        $profiler = new EnvironmentTracker($this->manager);

        // 1. Initial run to cache current configuration
        config(['agent-debugger.monitored_env_keys' => ['TEST_DRIFT_KEY']]);
        putenv('TEST_DRIFT_KEY=value-1');

        $profiler->monitor();

        // 2. Change env value and monitor again
        putenv('TEST_DRIFT_KEY=value-2');
        $profiler->monitor();

        $drifts = $this->manager->getConfigDrifts();

        $this->assertArrayHasKey('TEST_DRIFT_KEY', $drifts);
        $this->assertEquals('value-1', $drifts['TEST_DRIFT_KEY']['old']);
        $this->assertEquals('value-2', $drifts['TEST_DRIFT_KEY']['new']);

        // Clean up
        putenv('TEST_DRIFT_KEY');
    }
}
