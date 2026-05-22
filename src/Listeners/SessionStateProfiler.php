<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use LaravelAgentDebugger\DebugLoggerManager;

class SessionStateProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Extracts active non-framework session state parameters
     */
    public function profile(?Request $request = null): void
    {
        if (!config('agent-debugger.track_session', true)) {
            return;
        }

        $sessionData = [];
        try {
            // Retrieve session data from passed request, global request, or global facade
            $activeRequest = $request ?? (function_exists('request') ? request() : null);
            
            if ($activeRequest && $activeRequest->hasSession()) {
                $sessionData = $activeRequest->session()->all();
            } elseif (Session::isStarted()) {
                $sessionData = Session::all();
            } else {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        $filtered = [];

        // Core framework session keys to filter out from developer values
        $excludedKeys = [
            '_token',
            '_previous',
            '_flash',
            'url',
            'login_web_',
            'password_hash_web_',
        ];

        foreach ($sessionData as $key => $value) {
            $isExcluded = false;
            foreach ($excludedKeys as $excluded) {
                if (str_starts_with($key, $excluded)) {
                    $isExcluded = true;
                    break;
                }
            }

            if (!$isExcluded) {
                $filtered[$key] = $value;
            }
        }

        $this->manager->setSessionState($filtered);
    }
}
