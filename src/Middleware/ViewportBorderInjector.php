<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use LaravelAgentDebugger\DebugLoggerManager;

class ViewportBorderInjector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if configuration indicator is active
        if (!config('agent-debugger.show_frontend_indicator', true)) {
            return $response;
        }

        // Do not inject on AJAX, JSON, package's own routes, or SPA request headers
        if ($request->is('_agent_debug*') ||
            $request->ajax() ||
            $request->expectsJson() ||
            $request->hasHeader('X-Inertia') ||
            $request->hasHeader('X-Livewire')
        ) {
            return $response;
        }

        // Only inject on HTML standard response payloads
        $contentType = $response->headers->get('Content-Type') ?? '';
        if (!str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        // Find closing body tag
        $pos = strripos($content, '</body>');
        if ($pos === false) {
            return $response;
        }

        $indicatorHtml = $this->getIndicatorHtml();

        // Inject styled element before </body> tag
        $newContent = substr($content, 0, $pos) . $indicatorHtml . substr($content, $pos);
        $response->setContent($newContent);

        return $response;
    }

    /**
     * Renders a premium, glassmorphic floating dashboard status badge
     */
    protected function getIndicatorHtml(): string
    {
        $time = 0.0;
        $queries = 0;

        if (app()->bound(DebugLoggerManager::class)) {
            $manager = app(DebugLoggerManager::class);
            $time = $manager->getExecutionTime();
            $queries = count($manager->getQueries());
        }

        return "
<!-- Laravel Agent-Debugger Premium Viewport Status Badge -->
<div id=\"agent-debugger-badge\" style=\"
    position: fixed; 
    bottom: 16px; 
    right: 16px; 
    background: rgba(18, 18, 18, 0.85); 
    backdrop-filter: blur(8px); 
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15); 
    border-radius: 20px; 
    padding: 8px 16px; 
    color: #ffffff; 
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
    font-size: 12px; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4); 
    z-index: 999999; 
    pointer-events: auto;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
\" onmouseover=\"this.style.transform='scale(1.05) translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.6)';\" onmouseout=\"this.style.transform='scale(1) translateY(0)'; this.style.boxShadow='0 4px 16px rgba(0, 0, 0, 0.4)';\">
    <span style=\"display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 8px #ef4444; animation: agent-pulse 2s infinite;\"></span>
    <strong>Agent Active</strong>
    <span style=\"color: rgba(255, 255, 255, 0.25);\">|</span>
    <span>⚡ {$time}ms</span>
    <span style=\"color: rgba(255, 255, 255, 0.25);\">|</span>
    <span>🗄️ {$queries} Queries</span>
</div>

<style>
@keyframes agent-pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
";
    }
}
