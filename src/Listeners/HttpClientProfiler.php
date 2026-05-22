<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use LaravelAgentDebugger\DebugLoggerManager;

class HttpClientProfiler
{
    protected DebugLoggerManager $manager;
    protected array $requestStartTimes = [];

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Subscribe to outgoing HTTP clients event pipelines
     */
    public function subscribe(): void
    {
        // Capture receipt of HTTP responses
        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $method = $event->request->method();
            $url = $event->request->url();
            $status = $event->response->status();
            
            // Reconstruct duration metrics
            $duration = 0.0;
            if (method_exists($event->response, 'handlerStats')) {
                $stats = $event->response->handlerStats();
                $duration = round(($stats['total_time'] ?? 0.0) * 1000, 2);
            }

            $this->manager->addHttpCall($method, $url, $status, $duration);
        });

        // Capture failed connections (timeouts or network issues)
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event) {
            $method = $event->request->method();
            $url = $event->request->url();
            
            $this->manager->addHttpCall($method, $url, 500, 0.0);
        });
    }
}
