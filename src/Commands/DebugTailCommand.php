<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Commands;

use Illuminate\Console\Command;
use function Termwind\render;

class DebugTailCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:debug-tail {--file= : Specific log file to tail}';

    /**
     * The console command description.
     */
    protected $description = 'Stream high-fidelity agent debug requests in real-time with premium styling';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');

        $fileName = $this->option('file') ?? (
            $logStyle === 'single'
                ? 'agent_debug.log'
                : 'agent_debug-' . date('Y-m-d') . '.log'
        );

        $filePath = $logPath . '/' . $fileName;

        render('
            <div class="px-1 py-1 bg-red-600 text-white font-bold mb-1">
                🚀 Laravel Agent-Debugger Live Stream
            </div>
            <div class="text-gray-400 mb-1">Tailing file: <span class="text-white font-bold">' . $filePath . '</span></div>
            <div class="text-gray-500 italic mb-1">Waiting for incoming request actions... (Press Ctrl+C to exit)</div>
        ');

        if (!file_exists($filePath)) {
            // Touch the file so tailing can start cleanly
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            file_put_contents($filePath, '');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            render('<div class="text-red-500 font-bold">❌ Error: Could not open debug log file stream.</div>');
            return Command::FAILURE;
        }

        // Seek to the end of the file to only stream new requests
        fseek($handle, 0, SEEK_END);

        $buffer = '';

        while (true) {
            $line = fgets($handle);
            if ($line === false) {
                // Sleep briefly to avoid CPU thrashing
                usleep(100000);
                clearstatcache();
                continue;
            }

            $buffer .= $line;

            // Request blocks are demarcated by ================================================================================
            if (str_contains($line, '================================================================================')) {
                // If we have a complete request block, parse and output it beautifully
                if (trim($buffer) !== '================================================================================') {
                    $this->formatRequestBlock($buffer);
                }
                $buffer = '';
            }
        }

        fclose($handle);
        return Command::SUCCESS;
    }

    /**
     * Formats raw request blocks using Termwind markup
     */
    protected function formatRequestBlock(string $block): void
    {
        $lines = explode("\n", $block);
        $requestLine = '';
        $metaLine = '';
        $crashed = false;
        $warnings = [];
        $queries = [];
        $exceptions = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '=====')) {
                continue;
            }

            if (str_contains($trimmed, 'REQUEST:')) {
                $requestLine = str_replace('REQUEST:', '', $trimmed);
            } elseif (str_contains($trimmed, 'IP:')) {
                $metaLine = $trimmed;
                if (str_contains($trimmed, 'Response Status: 5') || str_contains($trimmed, 'CRASHED')) {
                    $crashed = true;
                }
            } elseif (str_contains($trimmed, '⚠️ WARNING:')) {
                $warnings[] = $trimmed;
            } elseif (str_contains($trimmed, 'select ') || str_contains($trimmed, 'update ') || str_contains($trimmed, 'insert ') || str_contains($trimmed, 'delete ')) {
                $queries[] = $trimmed;
            } elseif (str_contains($trimmed, 'Class: ') || str_contains($trimmed, 'Message: ') || str_contains($trimmed, 'File: ')) {
                $exceptions[] = $trimmed;
            }
        }

        // Render formatted block
        $badgeColor = $crashed ? 'bg-red-600' : 'bg-green-600';
        $metaColor = $crashed ? 'text-red-400' : 'text-green-400';

        render('
            <div class="border-l-4 border-gray-600 pl-2 py-1 my-1">
                <div class="flex space-x-2">
                    <span class="' . $badgeColor . ' text-white px-1 font-bold">HTTP</span>
                    <span class="text-white font-bold">' . htmlspecialchars($requestLine) . '</span>
                </div>
                <div class="' . $metaColor . ' text-xs">' . htmlspecialchars($metaLine) . '</div>
            </div>
        ');

        if (!empty($warnings)) {
            foreach ($warnings as $warning) {
                render('
                    <div class="bg-yellow-900 text-yellow-100 px-2 py-1 text-xs font-bold mb-1 border-l-4 border-yellow-500">
                        ' . htmlspecialchars($warning) . '
                    </div>
                ');
            }
        }

        if (!empty($exceptions)) {
            render('<div class="bg-red-950 text-red-200 px-2 py-1 text-xs border-l-4 border-red-500 font-mono mb-1">');
            render('<div class="text-red-400 font-bold">🔥 Application Exception Captured:</div>');
            foreach ($exceptions as $ex) {
                render('<div>' . htmlspecialchars($ex) . '</div>');
            }
            render('</div>');
        }

        if (!empty($queries)) {
            $slowCount = 0;
            foreach ($queries as $q) {
                if (str_contains($q, 'SLOW QUERY')) {
                    $slowCount++;
                }
            }
            $queryCount = count($queries);
            $slowText = $slowCount > 0 ? " (<span class='text-red-400 font-bold'>{$slowCount} SLOW</span>)" : "";
            
            render('
                <div class="text-gray-500 text-xs italic pl-2">
                    ⚡ Executed ' . $queryCount . ' database queries' . $slowText . '
                </div>
            ');
        }
    }
}
