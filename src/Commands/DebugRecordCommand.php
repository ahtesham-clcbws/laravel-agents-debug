<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Commands;

use Illuminate\Console\Command;

class DebugRecordCommand extends Command
{
    protected $signature = 'agent:debug-record {--limit=10 : Number of requests to capture}';
    protected $description = 'Export local agent-debugger request history to a portable diagnostic markdown report';

    public function handle(): int
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $filePath = $logPath . '/agent_debug.log';

        if (!file_exists($filePath)) {
            $this->error("No debugger logs found at '{$filePath}'. Run requests first!");
            return 1;
        }

        $content = file_get_contents($filePath);
        $blocks = array_filter(explode(str_repeat('=', 80), $content));
        
        $limit = (int)$this->option('limit');
        $blocks = array_slice(array_reverse($blocks), 0, $limit);

        $exportDir = storage_path('logs/agent-debugger');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $exportFile = $exportDir . "/debug_session_{$timestamp}.md";

        $md = [];
        $md[] = "# 🎙️ Portable Agent-Debugger Diagnostic Session Report";
        $md[] = "*Generated on " . date('Y-m-d H:i:s') . "*";
        $md[] = "";
        $md[] = "---";
        $md[] = "";

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;
            
            $method = 'GET';
            $url = '/';
            $status = 200;
            $duration = 0.0;
            $timestampStr = '';

            if (preg_match('/method:\s*"(.*?)"/', $block, $m)) $method = $m[1];
            if (preg_match('/url:\s*"(.*?)"/', $block, $m)) $url = $m[1];
            if (preg_match('/status:\s*(\d+)/', $block, $m)) $status = (int)$m[1];
            if (preg_match('/execution_time_ms:\s*([\d\.]+)/', $block, $m)) $duration = (float)$m[1];
            if (preg_match('/timestamp:\s*"(.*?)"/', $block, $m)) $timestampStr = $m[1];

            $md[] = "## [{$method} {$status}] {$url}";
            $md[] = "**Timestamp:** `{$timestampStr}` | **Latency:** `{$duration}ms`";
            $md[] = "";
            $md[] = "```yaml";
            $md[] = $block;
            $md[] = "```";
            $md[] = "";
            $md[] = "---";
            $md[] = "";
        }

        file_put_contents($exportFile, implode("\n", $md));
        
        $this->info("✨ Successfully exported portable session package to: {$exportFile}");
        return 0;
    }
}
