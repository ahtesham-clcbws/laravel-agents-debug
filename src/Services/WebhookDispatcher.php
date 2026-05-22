<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service to dispatch non-blocking webhook notifications on application crashes.
 */
class WebhookDispatcher
{
    /**
     * Sends crash notification embeds to Slack/Discord webhook channels.
     *
     * @param array<int, array{class: string, message: string, file: string, line: int}> $exceptions
     */
    public function dispatch(Request $request, Response $response, array $exceptions): void
    {
        $webhookUrl = config('agent-debugger.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $crashDetails = 'No exception trace captured.';
        if (!empty($exceptions)) {
            $ex = end($exceptions);
            $crashDetails = "**Class:** `{$ex['class']}`\n**Message:** `{$ex['message']}`\n**Location:** `{$ex['file']}:L{$ex['line']}`";
        }

        $payload = [
            'username' => 'Laravel Agent-Debugger',
            'avatar_url' => 'https://laravel.com/img/logomark.min.svg',
            'embeds' => [
                [
                    'title' => '🔥 Unhandled Application Crash Intercepted!',
                    'description' => $crashDetails,
                    'color' => 15548997,
                    'fields' => [
                        [
                            'name' => 'Method & URL',
                            'value' => "`{$request->getMethod()}` {$request->fullUrl()}",
                            'inline' => false
                        ],
                        [
                            'name' => 'IP Address',
                            'value' => $request->ip() ?? '127.0.0.1',
                            'inline' => true
                        ],
                        [
                            'name' => 'Response Status',
                            'value' => (string)$response->getStatusCode(),
                            'inline' => true
                        ]
                    ],
                    'timestamp' => date('c'),
                ]
            ]
        ];

        try {
            $options = [
                'http' => [
                    'header'  => "Content-Type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($payload),
                    'timeout' => 2.0,
                ]
            ];
            $context = stream_context_create($options);
            @file_get_contents((string)$webhookUrl, false, $context);
        } catch (\Throwable) {
            // Silence webhook delivery issues to remain low latency
        }
    }
}
