<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Event;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\KeyForgotten;
use LaravelAgentDebugger\DebugLoggerManager;

class CacheProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    public function subscribe(): void
    {
        Event::listen(CacheHit::class, function (CacheHit $event) {
            $size = $event->value !== null ? strlen(serialize($event->value)) : null;
            $this->manager->addCacheAction('HIT', $event->key, $size);
        });

        Event::listen(CacheMissed::class, function (CacheMissed $event) {
            $this->manager->addCacheAction('MISS', $event->key);
        });

        Event::listen(KeyWritten::class, function (KeyWritten $event) {
            $size = $event->value !== null ? strlen(serialize($event->value)) : null;
            $this->manager->addCacheAction('WRITE', $event->key, $size, $event->seconds);
        });

        Event::listen(KeyForgotten::class, function (KeyForgotten $event) {
            $this->manager->addCacheAction('FORGET', $event->key);
        });
    }
}
