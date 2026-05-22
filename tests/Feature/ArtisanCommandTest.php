<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests\Feature;

use LaravelAgentDebugger\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ArtisanCommandTest extends TestCase
{
    protected string $envFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->envFile = base_path('.env');
        
        // Setup empty .env file for command tests
        File::put($this->envFile, "APP_ENV=local\nAGENT_DEBUGGER_ENABLED=false\n");
    }

    protected function tearDown(): void
    {
        if (File::exists($this->envFile)) {
            File::delete($this->envFile);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function test_it_can_enable_debugger_via_artisan_command()
    {
        $this->artisan('agent:debug-on')
            ->expectsOutput('✅ Laravel Agent-Debugger has been successfully ENABLED!')
            ->assertExitCode(0);

        $envContent = File::get($this->envFile);
        $this->assertStringContainsString('AGENT_DEBUGGER_ENABLED=true', $envContent);
    }

    /** @test */
    public function test_it_can_disable_debugger_via_artisan_command()
    {
        $this->artisan('agent:debug-off')
            ->expectsOutput('🛑 Laravel Agent-Debugger has been successfully DISABLED.')
            ->assertExitCode(0);

        $envContent = File::get($this->envFile);
        $this->assertStringContainsString('AGENT_DEBUGGER_ENABLED=false', $envContent);
    }

    /** @test */
    public function test_it_can_output_status_via_artisan_command()
    {
        $this->artisan('agent:debug-status')
            ->expectsOutputToContain('Laravel Agent-Debugger Status Panel')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_can_clean_debug_logs_via_artisan_command()
    {
        $logFile = $this->tempLogPath . '/agent_debug.log';
        File::put($logFile, 'dummy log contents');

        $this->artisan('agent:debug-clean')
            ->expectsOutputToContain('🧹 Debug log workspace directory is fully clean!')
            ->assertExitCode(0);

        $this->assertStringEqualsFile($logFile, '');
    }
}
