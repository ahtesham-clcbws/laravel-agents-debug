<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobQueued;
use LaravelAgentDebugger\DebugLoggerManager;

class EventJobProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    public function subscribe(): void
    {
        // Listen to all custom application events
        Event::listen('*', function ($eventName, $data) {
            $this->logEvent($eventName, $data);
        });

        // Listen to background jobs queued
        Event::listen(JobQueued::class, function (JobQueued $event) {
            $jobName = is_string($event->job) ? $event->job : get_class($event->job);
            $queue = $event->connectionName . ':' . ($event->queue ?? 'default');
            
            $payload = null;
            if (is_object($event->job)) {
                $payload = [];
                foreach (get_object_vars($event->job) as $key => $val) {
                    if (is_object($val)) {
                        $payload[$key] = get_class($val);
                    } elseif (is_array($val)) {
                        $payload[$key] = '[Array]';
                    } else {
                        $payload[$key] = $val;
                    }
                }
            }
            $this->manager->addJob($jobName, $queue, $payload);
        });
    }

    /**
     * Filters and records custom dispatched events
     */
    protected function logEvent(string $eventName, $data): void
    {
        // Exclude typical core framework events to reduce logging noise
        if (
            str_starts_with($eventName, 'illuminate.') ||
            str_starts_with($eventName, 'eloquent.') ||
            str_starts_with($eventName, 'bootstrapping') ||
            str_starts_with($eventName, 'creating') ||
            str_starts_with($eventName, 'composer.')
        ) {
            return;
        }

        $payload = null;
        if (is_array($data)) {
            $payload = [];
            foreach ($data as $k => $v) {
                if (is_object($v)) {
                    $payload[$k] = get_class($v);
                } elseif (is_array($v)) {
                    $payload[$k] = '[Array]';
                } else {
                    $payload[$k] = $v;
                }
            }
        }

        $this->manager->addEvent($eventName, $payload);
    }
}
