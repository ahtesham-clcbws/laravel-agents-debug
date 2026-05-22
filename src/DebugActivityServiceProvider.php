<?php

declare(strict_types=1);

namespace LaravelAgentDebugger;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use LaravelAgentDebugger\Commands\DebugOnCommand;
use LaravelAgentDebugger\Commands\DebugOffCommand;
use LaravelAgentDebugger\Commands\DebugStatusCommand;
use LaravelAgentDebugger\Commands\DebugCleanCommand;
use LaravelAgentDebugger\Commands\DebugTailCommand;
use LaravelAgentDebugger\Middleware\DebugActivityLogger;
use LaravelAgentDebugger\Middleware\ViewportBorderInjector;

class DebugActivityServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge package configuration defaults
        $this->mergeConfigFrom(
            __DIR__ . '/../config/agent-debugger.php',
            'agent-debugger'
        );

        // Register package state manager
        $this->app->singleton(DebugLoggerManager::class, function ($app) {
            return new DebugLoggerManager($app);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(Kernel $kernel): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/agent-debugger.php' => config_path('agent-debugger.php'),
            ], 'agent-debugger-config');

            // Register Artisan commands
            $this->commands([
                DebugOnCommand::class,
                DebugOffCommand::class,
                DebugStatusCommand::class,
                DebugCleanCommand::class,
                DebugTailCommand::class,
            ]);

            // Auto-clean logs on local serve startup
            $this->autoCleanLogsOnServe();
        }

        // If not enabled globally, stop here
        if (!config('agent-debugger.enabled', false)) {
            return;
        }

        // Programmatically register the Global HTTP Middleware
        $kernel->prependMiddleware(DebugActivityLogger::class);

        // Register visual frame injector if enabled
        if (config('agent-debugger.show_frontend_indicator', true)) {
            $kernel->appendMiddleware(ViewportBorderInjector::class);
        }
    }

    /**
     * Automatically purges logs if started via php artisan serve commands
     */
    protected function autoCleanLogsOnServe(): void
    {
        if (!config('agent-debugger.auto_clean_debug', true)) {
            return;
        }

        $args = $_SERVER['argv'] ?? [];
        
        // Match: artisan serve
        $isServe = false;
        foreach ($args as $arg) {
            if ($arg === 'serve') {
                $isServe = true;
                break;
            }
        }

        if ($isServe) {
            $logPath = config('agent-debugger.log_path', storage_path('logs'));
            $logStyle = config('agent-debugger.log_style', 'date-wise');

            if (!is_dir($logPath)) {
                return;
            }

            if ($logStyle === 'single') {
                $target = $logPath . '/agent_debug.log';
                if (file_exists($target)) {
                    file_put_contents($target, '');
                }
            } else {
                $files = glob($logPath . '/agent_debug-*.log');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                }
            }
        }
    }
}
