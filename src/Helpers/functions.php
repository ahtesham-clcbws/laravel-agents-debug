<?php

declare(strict_types=1);

if (!function_exists('debug_log')) {
    /**
     * Expose premium manual debug logger to application scopes
     */
    function debug_log(string $message, array $context = []): void
    {
        if (!config('agent-debugger.enabled', false)) {
            return;
        }

        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');

        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $fileName = $logStyle === 'single'
            ? 'agent_debug.log'
            : 'agent_debug-' . date('Y-m-d') . '.log';

        $filePath = $logPath . '/' . $fileName;

        $timestamp = date('Y-m-d H:i:s');
        $formattedContext = !empty($context) ? json_encode($context, JSON_PRETTY_PRINT) : '';
        
        $output = "[{$timestamp}] MANUAL LOG: {$message} {$formattedContext}\n";

        file_put_contents($filePath, $output, FILE_APPEND);
    }
}

if (!function_exists('debug_span')) {
    /**
     * Measure and record execution time for a custom closure block
     */
    function debug_span(string $name, Closure $callback)
    {
        if (!config('agent-debugger.enabled', false)) {
            return $callback();
        }

        $start = microtime(true);
        try {
            return $callback();
        } finally {
            $duration = round((microtime(true) - $start) * 1000, 2);
            if (app()->bound(LaravelAgentDebugger\DebugLoggerManager::class)) {
                app(LaravelAgentDebugger\DebugLoggerManager::class)->addSpan($name, $duration);
            }
        }
    }
}
