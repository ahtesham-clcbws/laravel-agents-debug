<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Event;
use LaravelAgentDebugger\DebugLoggerManager;

class EloquentProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    public function subscribe(): void
    {
        Event::listen('eloquent.*', function (string $event, array $data) {
            if (empty($data)) {
                return;
            }

            $model = $data[0];
            if (!is_object($model)) {
                return;
            }

            $modelClass = get_class($model);
            $parts = explode(':', $event);
            $actionEvent = str_replace('eloquent.', '', $parts[0] ?? '');

            if (in_array($actionEvent, ['retrieved', 'booting', 'booted'], true)) {
                return;
            }

            $this->manager->addEloquentEvent([
                'event' => $actionEvent,
                'model' => $modelClass,
                'id' => method_exists($model, 'getKey') ? $model->getKey() : null,
                'time' => microtime(true)
            ]);
        });
    }
}
