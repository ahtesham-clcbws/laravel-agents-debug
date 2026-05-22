<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Gate;
use LaravelAgentDebugger\DebugLoggerManager;

class AuthorizationProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Subscribe to Gate policy checks
     */
    public function subscribe(): void
    {
        if (!config('agent-debugger.track_gates', true)) {
            return;
        }

        // Gate::after evaluates after policy and returns decision result state
        Gate::after(function ($user, string $ability, bool $result, array $arguments) {
            $formattedArgs = $this->serializeArguments($arguments);
            $outcome = $result ? 'ALLOWED' : 'DENIED';

            $this->manager->addGate($ability, $outcome, $formattedArgs);
        });
    }

    /**
     * Formats class paths and model keys into short human-readable values
     */
    protected function serializeArguments(array $arguments): ?string
    {
        if (empty($arguments)) {
            return null;
        }

        $serialized = [];
        foreach ($arguments as $arg) {
            if (is_object($arg)) {
                $class = basename(str_replace('\\', '/', get_class($arg)));
                $key = method_exists($arg, 'getKey') ? $arg->getKey() : '';
                $serialized[] = $key !== '' ? "{$class} #{$key}" : $class;
            } elseif (is_array($arg)) {
                $serialized[] = 'Array';
            } else {
                $serialized[] = (string)$arg;
            }
        }

        return implode(', ', $serialized);
    }
}
