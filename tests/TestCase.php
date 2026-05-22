<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use LaravelAgentDebugger\DebugActivityServiceProvider;
use Illuminate\Support\Facades\File;

abstract class TestCase extends OrchestraTestCase
{
    protected string $tempLogPath;

    /**
     * Setup the test environment before execution
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogPath = __DIR__ . '/../storage/logs';
        
        if (!file_exists($this->tempLogPath)) {
            mkdir($this->tempLogPath, 0755, true);
        }

        // Configure test configurations path
        config(['agent-debugger.log_path' => $this->tempLogPath]);
    }

    /**
     * Clean up test logs after execution
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempLogPath)) {
            File::deleteDirectory($this->tempLogPath);
        }

        parent::tearDown();
    }

    /**
     * Register package service providers
     */
    protected function getPackageProviders($app): array
    {
        return [
            DebugActivityServiceProvider::class,
        ];
    }

    /**
     * Define active database migrations and environment setups
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set in-memory SQLite database connection
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
