<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

/**
 * Service to parse compiled YAML/text blocks from the request log.
 */
class LogParser
{
    /**
     * Parse and extract requests from the compiled debugger log.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getParsedLogs(): array
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');
        $fileName = $logStyle === 'single' ? 'agent_debug.log' : 'agent_debug-' . date('Y-m-d') . '.log';
        $filePath = $logPath . '/' . $fileName;

        if (!file_exists($filePath)) {
            return [];
        }

        $content = (string)file_get_contents($filePath);
        $blocks = explode(str_repeat('=', 80), $content);
        $parsed = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }
            $parsed[] = BlockParser::parseBlock($block);
        }

        return array_reverse($parsed);
    }
}
