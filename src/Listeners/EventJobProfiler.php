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

    /**
     * Subscribe to events and queue dispatch pipelines
     */
    public function subscribe(): void
    {
        // Listen to all custom application events
        Event::listen('*', function ($eventName, $data) {
            $this->logEvent($eventName);
        });

        // Listen to background jobs queued
        Event::listen(JobQueued::class, function (JobQueued $event) {
            $jobName = is_string($event->job) ? $event->job : get_class($event->job);
            $queue = $event->connectionName . ':' . ($event->queue ?? 'default');
            $this->manager->addJob($jobName, $queue);
        });
    }

    /**
     * Filters and records custom dispatched events
     */
    protected function logEvent(string $eventName): void
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

        $this->manager->addEvent($eventName);
    }
}
