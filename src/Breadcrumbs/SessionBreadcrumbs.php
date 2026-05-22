<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Breadcrumbs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SessionBreadcrumbs
{
    protected const SESSION_KEY = 'agent_debugger_breadcrumbs';

    /**
     * Push active incoming request route parameters onto session trail history array
     */
    public function push(Request $request): void
    {
        if (!config('agent-debugger.enabled', false)) {
            return;
        }

        try {
            if (!$request->hasSession()) {
                return;
            }
        } catch (\Exception $e) {
            return; // Safety fallback if session engine is disabled
        }

        $breadcrumbs = Session::get(self::SESSION_KEY, []);
        $limit = config('agent-debugger.breadcrumb_limit', 10);

        // Strip query tokens from URL for clean rendering
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        
        $newBreadcrumb = [
            'method' => $method,
            'path' => $path,
            'status' => null, // Populated inside response capture terminate hook
            'time' => date('Y-m-d H:i:s'),
        ];

        $breadcrumbs[] = $newBreadcrumb;

        // Truncate to maximum limit ring buffer
        if (count($breadcrumbs) > $limit) {
            array_shift($breadcrumbs);
        }

        Session::put(self::SESSION_KEY, $breadcrumbs);
    }

    /**
     * Update the latest breadcrumb item with the response HTTP status code
     */
    public function updateLastResponseStatus(int $statusCode): void
    {
        try {
            if (!Session::isStarted()) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        $breadcrumbs = Session::get(self::SESSION_KEY, []);
        
        if (!empty($breadcrumbs)) {
            $lastIndex = count($breadcrumbs) - 1;
            $breadcrumbs[$lastIndex]['status'] = $statusCode;
            Session::put(self::SESSION_KEY, $breadcrumbs);
        }
    }

    /**
     * Extract active session list
     */
    public static function get(): array
    {
        try {
            if (!Session::isStarted()) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        return Session::get(self::SESSION_KEY, []);
    }
}
