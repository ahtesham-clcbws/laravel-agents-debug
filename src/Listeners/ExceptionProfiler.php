<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use LaravelAgentDebugger\DebugLoggerManager;

class ExceptionProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Subscribe to logging listener streams
     */
    public function subscribe(): void
    {
        Log::listen(function ($message) {
            $this->captureLoggedError($message);
        });
    }

    /**
     * Parse logged exceptions and warnings
     */
    protected function captureLoggedError($event): void
    {
        $context = $event->context ?? [];
        $exception = $context['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            $this->logThrowable($exception, true);
        }
    }

    /**
     * Formats exception properties and callstack lines
     */
    public function logThrowable(\Throwable $throwable, bool $isSwallowed = false): void
    {
        $class = get_class($throwable);
        $message = $throwable->getMessage();
        $file = $throwable->getFile();
        $line = $throwable->getLine();

        // Check if the exception originates from a compiled Blade view file
        $bladeContext = null;
        if ($throwable instanceof ViewException || str_contains($file, 'storage/framework/views')) {
            $bladeContext = $this->resolveBladeException($file, $line);
            if ($bladeContext) {
                $file = $bladeContext['file'];
                $line = $bladeContext['line'];
            }
        }

        // Clean stack trace showing only application directories
        $cleanTrace = [];
        $trace = $throwable->getTrace();
        $count = 0;

        foreach ($trace as $step) {
            if (isset($step['file']) && !str_contains($step['file'], 'vendor/') && $count < 8) {
                $cleanTrace[] = basename($step['file']) . ' line ' . ($step['line'] ?? 0) . ' -> ' . ($step['function'] ?? '');
                $count++;
            }
        }

        $this->manager->addException([
            'class' => $class,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'is_swallowed' => $isSwallowed,
            'blade_line_content' => $bladeContext['content'] ?? null,
            'trace' => $cleanTrace,
        ]);
    }

    /**
     * Resolves compiled storage view files back to raw physical .blade.php structures
     */
    protected function resolveBladeException(string $compiledFile, int $line): ?array
    {
        if (!file_exists($compiledFile)) {
            return null;
        }

        $content = @file_get_contents($compiledFile);
        if (!$content) {
            return null;
        }

        // Search for PATH comment appended at the end of compiled cached views
        if (preg_match('/PATH\s+(\S+)\s+PATH/', $content, $matches)) {
            $rawBladePath = $matches[1];

            if (file_exists($rawBladePath)) {
                $lines = @file($rawBladePath);
                $lineContent = isset($lines[$line - 1]) ? $lines[$line - 1] : 'Line content not resolvable.';
                
                return [
                    'file' => $rawBladePath,
                    'line' => $line,
                    'content' => trim($lineContent)
                ];
            }
        }

        return null;
    }
}
