<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests\Feature;

use LaravelAgentDebugger\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DashboardRoutesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        
        $app['config']->set('agent-debugger.enabled', true);
        $app['config']->set('agent-debugger.show_frontend_indicator', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure there is a mocks.json or log dir we can test with
        $mocksFile = storage_path('logs/agent-debugger/mocks.json');
        if (File::exists($mocksFile)) {
            File::delete($mocksFile);
        }
    }

    protected function tearDown(): void
    {
        $mocksFile = storage_path('logs/agent-debugger/mocks.json');
        if (File::exists($mocksFile)) {
            File::delete($mocksFile);
        }
        parent::tearDown();
    }

    /** @test */
    public function test_dashboard_route_returns_html()
    {
        $response = $this->get('/_agent_debug/dashboard');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('Laravel Agent-Debugger Dashboard', $response->getContent());
    }

    /** @test */
    public function test_logs_route_returns_json()
    {
        $response = $this->get('/_agent_debug/logs');
        $response->assertStatus(200);
        $response->assertJsonStructure([]);
    }

    /** @test */
    public function test_artisan_routes_work_for_valid_commands()
    {
        $response = $this->postJson('/_agent_debug/artisan/cache-clear');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Cache cleared successfully!'
        ]);
    }

    /** @test */
    public function test_artisan_routes_fail_for_invalid_commands()
    {
        $response = $this->postJson('/_agent_debug/artisan/unknown-command');
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Command not found.'
        ]);
    }

    /** @test */
    public function test_explain_route_validates_query()
    {
        $response = $this->postJson('/_agent_debug/explain', ['sql' => '']);
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'No SQL query provided.']);

        $response = $this->postJson('/_agent_debug/explain', ['sql' => 'INSERT INTO users VALUES (1)']);
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'Only SELECT queries can be explained.']);
    }

    /** @test */
    public function test_explain_route_runs_explain()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite driver is not available.');
        }

        DB::statement('CREATE TABLE dummy_table (id INTEGER PRIMARY KEY)');

        $response = $this->postJson('/_agent_debug/explain', ['sql' => 'SELECT * FROM dummy_table']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'explain']);
    }

    /** @test */
    public function test_playground_route_validates_query()
    {
        $response = $this->postJson('/_agent_debug/playground', ['sql' => '']);
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'No SQL query provided.']);

        $response = $this->postJson('/_agent_debug/playground', ['sql' => 'DELETE FROM users']);
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'Only SELECT queries are allowed in the SQL Playground.']);
    }

    /** @test */
    public function test_playground_route_runs_query()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite driver is not available.');
        }

        DB::statement('CREATE TABLE dummy_table2 (id INTEGER PRIMARY KEY)');
        DB::statement('INSERT INTO dummy_table2 (id) VALUES (10), (20)');

        $response = $this->postJson('/_agent_debug/playground', ['sql' => 'SELECT id FROM dummy_table2']);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'results']);
        $this->assertCount(2, $response->json('results'));
    }

    /** @test */
    public function test_mocks_routes_read_and_write()
    {
        $response = $this->get('/_agent_debug/mocks');
        $response->assertStatus(200);
        $this->assertEquals([], $response->json());

        $mockData = [
            ['url_pattern' => 'example.com/*', 'response_body' => ['ok' => true], 'status' => 200]
        ];

        $response = $this->postJson('/_agent_debug/mock-save', ['mocks' => $mockData]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $response = $this->get('/_agent_debug/mocks');
        $response->assertStatus(200);
        $response->assertJson($mockData);
    }

    /** @test */
    public function test_assets_routes_serve_files()
    {
        $response = $this->get('/_agent_debug/assets/vue.js');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

        $response = $this->get('/_agent_debug/assets/tailwind.js');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
    }
}
