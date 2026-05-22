<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Cache;
use LaravelAgentDebugger\DebugLoggerManager;

class EnvironmentTracker
{
    protected DebugLoggerManager $manager;
    protected const CACHE_KEY = 'agent_debugger_env_hash';
    protected const CACHE_VALUES_KEY = 'agent_debugger_env_values';

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Inspects configuration states and logs differences compared to last requests
     */
    public function monitor(): void
    {
        if (!config('agent-debugger.track_config_drift', true)) {
            return;
        }

        $monitoredKeys = config('agent-debugger.monitored_env_keys', [
            'DB_CONNECTION',
            'SESSION_DRIVER',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
        ]);

        $currentValues = [];
        foreach ($monitoredKeys as $key) {
            $currentValues[$key] = env($key, 'not_set');
        }

        // Retrieve historical state parameters from local cache
        $oldValues = Cache::get(self::CACHE_VALUES_KEY, []);

        if (!empty($oldValues)) {
            foreach ($currentValues as $key => $value) {
                $oldValue = $oldValues[$key] ?? 'not_set';
                if ($value !== $oldValue) {
                    $this->manager->addConfigDrift($key, (string)$oldValue, (string)$value);
                }
            }
        }

        // Cache current configurations for the next request validation sequence
        Cache::put(self::CACHE_VALUES_KEY, $currentValues, 86400); // 24 hours TTL
    }
}
