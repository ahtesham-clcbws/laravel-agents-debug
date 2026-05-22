<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelAgentDebugger\Services\LogParser;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller to handle the main agent-debugger dashboard view and log feeds.
 */
final class DashboardController
{
    /**
     * Render the main visual diagnostic dashboard layout.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function index()
    {
        return view('agent-debugger::dashboard');
    }

    /**
     * Retrieve structured request logs parsed from localized text logs.
     *
     * @param LogParser $parser
     * @return JsonResponse
     */
    public function logs(LogParser $parser): JsonResponse
    {
        return response()->json($parser->getParsedLogs());
    }

    /**
     * Stream real-time diagnostic logs to the frontend via Server-Sent Events (SSE).
     *
     * @param LogParser $parser
     * @return StreamedResponse
     */
    public function sse(LogParser $parser): StreamedResponse
    {
        return response()->stream(function () use ($parser): void {
            $lastCount = 0;
            $startTime = time();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $logs = $parser->getParsedLogs();
                $currentCount = count($logs);

                if ($currentCount !== $lastCount) {
                    $lastCount = $currentCount;
                    echo "data: " . json_encode($logs) . "\n\n";
                    ob_flush();
                    flush();
                }

                if (time() - $startTime > 300) {
                    break;
                }

                usleep(1000000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Serve Vue JS asset offline.
     */
    public function serveVue()
    {
        $path = __DIR__ . '/../../resources/assets/vue.global.js';
        if (!file_exists($path)) {
            abort(404);
        }
        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/javascript',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Serve Tailwind JS asset offline.
     */
    public function serveTailwind()
    {
        $path = __DIR__ . '/../../resources/assets/tailwind.js';
        if (!file_exists($path)) {
            abort(404);
        }
        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/javascript',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
