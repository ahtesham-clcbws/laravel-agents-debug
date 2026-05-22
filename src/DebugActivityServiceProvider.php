<?php

declare(strict_types=1);

namespace LaravelAgentDebugger;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use LaravelAgentDebugger\Commands\DebugCleanCommand;
use LaravelAgentDebugger\Commands\DebugOffCommand;
use LaravelAgentDebugger\Commands\DebugOnCommand;
use LaravelAgentDebugger\Commands\DebugRecordCommand;
use LaravelAgentDebugger\Commands\DebugStatusCommand;
use LaravelAgentDebugger\Commands\DebugTailCommand;
use LaravelAgentDebugger\Controllers\ArtisanController;
use LaravelAgentDebugger\Controllers\DashboardController;
use LaravelAgentDebugger\Controllers\DatabaseController;
use LaravelAgentDebugger\Controllers\MocksController;
use LaravelAgentDebugger\Middleware\DebugActivityLogger;
use LaravelAgentDebugger\Middleware\ViewportBorderInjector;

/**
 * Service provider to bootstrap the package, config, routes, middleware, and command capabilities.
 */
class DebugActivityServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/agent-debugger.php',
            'agent-debugger'
        );

        $this->app->singleton(DebugLoggerManager::class, function ($app) {
            return new DebugLoggerManager($app);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(Kernel $kernel): void
    {
        $this->applyHttpMocks();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/agent-debugger.php' => config_path('agent-debugger.php'),
            ], 'agent-debugger-config');

            $this->commands([
                DebugOnCommand::class,
                DebugOffCommand::class,
                DebugStatusCommand::class,
                DebugCleanCommand::class,
                DebugTailCommand::class,
                DebugRecordCommand::class,
            ]);

            $this->autoCleanLogsOnServe();
        }

        if (! config('agent-debugger.enabled', false)) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agent-debugger');

        $this->registerRoutes();

        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(DebugActivityLogger::class);
        }

        if (config('agent-debugger.show_frontend_indicator', true)) {
            if (method_exists($kernel, 'appendMiddleware')) {
                $kernel->appendMiddleware(ViewportBorderInjector::class);
            } elseif (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(ViewportBorderInjector::class);
            }
        }
    }

    /**
     * Apply mocked HTTP responses if configured.
     */
    protected function applyHttpMocks(): void
    {
        $mocksFile = storage_path('logs/agent-debugger/mocks.json');
        if (file_exists($mocksFile)) {
            $mocks = json_decode((string) file_get_contents($mocksFile), true) ?: [];
            if (! empty($mocks)) {
                $fakeRules = [];
                foreach ($mocks as $m) {
                    $url = $m['url_pattern'] ?? '';
                    if ($url) {
                        $fakeRules[$url] = \Illuminate\Support\Facades\Http::response(
                            $m['response_body'] ?? [],
                            $m['status'] ?? 200,
                            $m['headers'] ?? []
                        );
                    }
                }
                if (! empty($fakeRules)) {
                    \Illuminate\Support\Facades\Http::fake($fakeRules);
                }
            }
        }
    }

    /**
     * Automatically purges logs if started via php artisan serve commands.
     */
    protected function autoCleanLogsOnServe(): void
    {
        if (! config('agent-debugger.auto_clean_debug', true)) {
            return;
        }

        $args = $_SERVER['argv'] ?? [];
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

            if (! is_dir($logPath)) {
                return;
            }

            if ($logStyle === 'single') {
                $target = $logPath.'/agent_debug.log';
                if (file_exists($target)) {
                    file_put_contents($target, '');
                }
            } else {
                $files = glob($logPath.'/agent_debug-*.log');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            @unlink($file);
                        }
                    }
                }
            }

            $inertiaFiles = glob($logPath.'/agent-debugger/inertia/*.json');
            if (is_array($inertiaFiles)) {
                foreach ($inertiaFiles as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Register package dashboard routes.
     */
    protected function registerRoutes(): void
    {
        $router = $this->app->make('router');

        $router->get('_agent_debug/dashboard', [DashboardController::class, 'index']);
        $router->get('_agent_debug/logs', [DashboardController::class, 'logs']);
        $router->get('_agent_debug/sse', [DashboardController::class, 'sse']);
        $router->post('_agent_debug/artisan/{command}', [ArtisanController::class, 'runArtisan']);
        $router->post('_agent_debug/explain', [DatabaseController::class, 'explain']);
        $router->post('_agent_debug/playground', [DatabaseController::class, 'playground']);
        $router->post('_agent_debug/run-tests', [ArtisanController::class, 'runTests']);
        $router->get('_agent_debug/mocks', [MocksController::class, 'index']);
        $router->post('_agent_debug/mock-save', [MocksController::class, 'save']);
        $router->get('_agent_debug/assets/vue.js', [DashboardController::class, 'serveVue']);
        $router->get('_agent_debug/assets/tailwind.js', [DashboardController::class, 'serveTailwind']);
    }
}
