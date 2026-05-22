<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use LaravelAgentDebugger\DebugLoggerManager;
use LaravelAgentDebugger\Breadcrumbs\SessionBreadcrumbs;
use LaravelAgentDebugger\Listeners\QueryProfiler;
use LaravelAgentDebugger\Listeners\ViewProfiler;
use LaravelAgentDebugger\Listeners\ExceptionProfiler;
use LaravelAgentDebugger\Listeners\EventJobProfiler;
use LaravelAgentDebugger\Listeners\HttpClientProfiler;
use LaravelAgentDebugger\Listeners\AuthorizationProfiler;
use LaravelAgentDebugger\Listeners\SessionStateProfiler;
use LaravelAgentDebugger\Listeners\EnvironmentTracker;

class DebugActivityLogger
{
    protected DebugLoggerManager $manager;
    protected SessionBreadcrumbs $breadcrumbs;
    protected QueryProfiler $queryProfiler;
    protected ViewProfiler $viewProfiler;
    protected ExceptionProfiler $exceptionProfiler;
    protected EventJobProfiler $eventJobProfiler;
    protected HttpClientProfiler $httpClientProfiler;
    protected AuthorizationProfiler $authorizationProfiler;
    protected SessionStateProfiler $sessionStateProfiler;
    protected EnvironmentTracker $environmentTracker;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
        $this->breadcrumbs = new SessionBreadcrumbs();
        $this->queryProfiler = new QueryProfiler($manager);
        $this->viewProfiler = new ViewProfiler($manager);
        $this->exceptionProfiler = new ExceptionProfiler($manager);
        $this->eventJobProfiler = new EventJobProfiler($manager);
        $this->httpClientProfiler = new HttpClientProfiler($manager);
        $this->authorizationProfiler = new AuthorizationProfiler($manager);
        $this->sessionStateProfiler = new SessionStateProfiler($manager);
        $this->environmentTracker = new EnvironmentTracker($manager);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip logging if URL matches exceptions list
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Initialize session breadcrumb parameters
        $this->breadcrumbs->push($request);

        // Register core event profiler listeners
        $this->queryProfiler->subscribe();
        $this->viewProfiler->subscribe();
        $this->exceptionProfiler->subscribe();
        $this->eventJobProfiler->subscribe();
        $this->httpClientProfiler->subscribe();
        $this->authorizationProfiler->subscribe();

        // Capture User authentication details
        $this->resolveAuthenticatedUser();

        try {
            $response = $next($request);
            
            // Record status code inside session breadcrumb trails
            $this->breadcrumbs->updateLastResponseStatus($response->getStatusCode());
            
            return $response;
        } catch (\Throwable $throwable) {
            // Capture unhandled app exceptions before bubbling them
            $this->exceptionProfiler->logThrowable($throwable, false);
            
            $this->breadcrumbs->updateLastResponseStatus(500);
            
            throw $throwable;
        }
    }

    /**
     * Compile metrics and record data to file upon successful response delivery
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        // Compile custom sessions and monitor environmental shifts
        $this->sessionStateProfiler->profile($request);
        $this->environmentTracker->monitor();

        // Formats final log content
        $logOutput = $this->compileLogOutput($request, $response);

        // Writes compilation to file based on configured parameters
        $this->writeLogToFile($logOutput);

        // Dispatch crash notifications on webhook endpoints if config parameters are present
        if ($response->getStatusCode() >= 500) {
            $this->dispatchWebhookNotification($request, $response);
        }
    }

    /**
     * Sends crash notification embeds to Slack/Discord webhook channels
     */
    protected function dispatchWebhookNotification(Request $request, Response $response): void
    {
        $webhookUrl = config('agent-debugger.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $exceptions = $this->manager->getExceptions();
        $crashDetails = 'No exception trace captured.';
        if (!empty($exceptions)) {
            $ex = end($exceptions);
            $crashDetails = "**Class:** `{$ex['class']}`\n**Message:** `{$ex['message']}`\n**Location:** `{$ex['file']}:L{$ex['line']}`";
        }

        $payload = [
            'username' => 'Laravel Agent-Debugger',
            'avatar_url' => 'https://laravel.com/img/logomark.min.svg',
            'embeds' => [
                [
                    'title' => '🔥 Unhandled Application Crash Intercepted!',
                    'description' => $crashDetails,
                    'color' => 15548997,
                    'fields' => [
                        [
                            'name' => 'Method & URL',
                            'value' => "`{$request->getMethod()}` {$request->fullUrl()}",
                            'inline' => false
                        ],
                        [
                            'name' => 'IP Address',
                            'value' => $request->ip() ?? '127.0.0.1',
                            'inline' => true
                        ],
                        [
                            'name' => 'Response Status',
                            'value' => (string)$response->getStatusCode(),
                            'inline' => true
                        ]
                    ],
                    'timestamp' => date('c'),
                ]
            ]
        ];

        try {
            $options = [
                'http' => [
                    'header'  => "Content-Type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($payload),
                    'timeout' => 2.0,
                ]
            ];
            $context = stream_context_create($options);
            @file_get_contents($webhookUrl, false, $context);
        } catch (\Throwable $e) {
            // Silence webhook delivery issues to remain low latency
        }
    }

    /**
     * Evaluates route URLs to exclude framework pages from logs
     */
    protected function shouldSkip(Request $request): bool
    {
        $exceptions = config('agent-debugger.except', []);
        foreach ($exceptions as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Maps active User model properties based on configuration parameters
     */
    protected function resolveAuthenticatedUser(): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $id = $user->getKey();
            
            $identifier = 'User';
            $keys = config('agent-debugger.auth_identifiers', ['email', 'username', 'name']);
            
            foreach ($keys as $key) {
                if (!empty($user->{$key})) {
                    $identifier = (string)$user->{$key};
                    break;
                }
            }

            $class = basename(str_replace('\\', '/', get_class($user)));
            $this->manager->setActiveUser([
                'id' => $id,
                'class' => $class,
                'identifier' => $identifier,
            ]);
        }
    }

    /**
     * Compiles fully formatted high-fidelity diagnostics output string
     */
    protected function compileLogOutput(Request $request, Response $response): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $method = $request->getMethod();
        $url = $request->fullUrl();
        $ip = $request->ip() ?? '127.0.0.1';
        
        $duration = $this->manager->getExecutionTime();
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        $routeAction = $request->route() ? $request->route()->getActionName() : 'N/A';
        $referer = $request->header('referer', 'N/A');

        // Compile User Header Context
        $userString = 'Guest';
        $activeUser = $this->manager->getActiveUser();
        if ($activeUser) {
            $userString = "User #{$activeUser['id']} (" . strtolower($activeUser['class']) . ": {$activeUser['identifier']})";
        }

        $crashedSuffix = $response->getStatusCode() >= 500 ? ' (CRASHED)' : '';
        $statusCode = $response->getStatusCode() . $crashedSuffix;

        $log = [];
        $log[] = str_repeat('=', 80);
        $log[] = "[{$timestamp}] REQUEST: {$method} {$url}";
        $log[] = "IP: {$ip} | Auth: {$userString} | Execution: {$duration}ms | Memory Peak: {$memory} MB";
        $log[] = "Route Action: {$routeAction}";
        $log[] = "Referer: {$referer} | Response Status: {$statusCode}";

        // Config Drifts
        $drifts = $this->manager->getConfigDrifts();
        if (!empty($drifts)) {
            $log[] = "";
            $log[] = "⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!";
            foreach ($drifts as $key => $drift) {
                $log[] = "  - {$key} changed from '{$drift['old']}' to '{$drift['new']}'";
            }
        }

        // Headers
        $headers = $this->redactArray($request->headers->all(), config('agent-debugger.redact_headers', []));
        $log[] = "";
        $log[] = "- INCOMING REQUEST HEADERS:";
        foreach ($headers as $key => $val) {
            $valStr = is_array($val) ? implode(', ', $val) : (string)$val;
            $log[] = "  * {$key}: {$valStr}";
        }

        // Navigation Breadcrumbs
        $breadcrumbs = SessionBreadcrumbs::get();
        if (!empty($breadcrumbs)) {
            $log[] = "";
            $log[] = "- SESSION BREADCRUMB TRAIL:";
            $count = 1;
            foreach ($breadcrumbs as $crumb) {
                $statusStr = $crumb['status'] ? " [{$crumb['status']}]" : ' [N/A]';
                $log[] = "  {$count}.{$statusStr} {$crumb['method']} {$crumb['path']}";
                $count++;
            }
        }

        // Active Session Variables
        $session = $this->manager->getSessionState();
        if (!empty($session)) {
            $log[] = "";
            $log[] = "- ACTIVE SESSION STATE:";
            foreach ($session as $key => $val) {
                $valStr = is_scalar($val) ? (string)$val : json_encode($val);
                $log[] = "  * {$key} => {$valStr}";
            }
        }

        // Authorization Gate Results
        $gates = $this->manager->getGates();
        if (!empty($gates)) {
            $log[] = "";
            $log[] = "- AUTHORIZATION GATES & POLICIES:";
            foreach ($gates as $gate) {
                $argStr = $gate['arguments'] ? " (Arguments: {$gate['arguments']})" : '';
                $log[] = "  * [{$gate['result']}] {$gate['ability']}{$argStr}";
            }
        }

        // Request Payloads (Redacted & Truncated)
        $payload = $this->redactArray($request->all());
        $payload = $this->truncateArrayPayload($payload);
        $log[] = "";
        $log[] = "- INCOMING REQUEST PAYLOAD:";
        $log[] = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // SQL Database transaction actions and Queries
        $queries = $this->manager->getQueries();
        $transactions = $this->manager->getTransactions();
        
        if (!empty($queries) || !empty($transactions)) {
            $log[] = "";
            $log[] = "- DATABASE TRANSACTION & QUERIES:";
            
            // Reconstruct interleaving sequences by using chronologies
            foreach ($transactions as $tx) {
                $suffix = $tx['fileLine'] ? " (Triggered on {$tx['fileLine']})" : '';
                $log[] = "  * [0.00ms] {$tx['type']}{$suffix}";
            }
            
            foreach ($queries as $q) {
                $prefix = $q['is_slow'] ? "[SLOW QUERY ({$q['time']}ms)]" : "[{$q['time']}ms]";
                $suffix = !empty($q['fileLine']) ? " (Fired at {$q['fileLine']})" : '';
                $log[] = "  * {$prefix} {$q['sql']}{$suffix}";
            }

            // Append Eager load loop warnings
            $repeats = $this->queryProfiler->getRepeatingQueries();
            if (!empty($repeats)) {
                $log[] = "";
                foreach ($repeats as $rep) {
                    $log[] = "  ⚠️ WARNING: N+1 Query Detected! The following query executed {$rep['count']} times:";
                    $log[] = "  * {$rep['query']}";
                    $log[] = "  👉 Solution: {$rep['remedy']}";
                }
            }
        }

        // View Compositions
        $views = $this->manager->getViews();
        if (!empty($views)) {
            $log[] = "";
            $log[] = "- VIEW COMPOSITIONS:";
            foreach ($views as $view) {
                $log[] = "  * {$view}";
            }
        }

        // Outgoing HTTP Requests
        $calls = $this->manager->getHttpCalls();
        if (!empty($calls)) {
            $log[] = "";
            $log[] = "- OUTGOING EXTERNAL HTTP REQUESTS:";
            foreach ($calls as $c) {
                $log[] = "  * [{$c['method']} {$c['status']}] {$c['url']} (Duration: {$c['duration']}ms)";
            }
        }

        // Performance Spans
        $spans = $this->manager->getSpans();
        if (!empty($spans)) {
            $log[] = "";
            $log[] = "- PERFORMANCE SPANS:";
            foreach ($spans as $s) {
                $log[] = "  * [{$s['duration']}ms] {$s['name']}";
            }
        }

        // Events & Jobs dispatches
        $events = $this->manager->getEvents();
        $jobs = $this->manager->getJobs();
        
        if (!empty($events) || !empty($jobs)) {
            $log[] = "";
            $log[] = "- SIDE-EFFECT EVENTS & JOBS:";
            foreach ($events as $e) {
                $log[] = "  * [Event] {$e}";
            }
            foreach ($jobs as $j) {
                $log[] = "  * [Job] {$j['name']} (Dispatched to '{$j['queue']}' queue)";
            }
        }

        // Blade Engine Crash / Swallow Exception Details
        $exceptions = $this->manager->getExceptions();
        if (!empty($exceptions)) {
            foreach ($exceptions as $ex) {
                $header = $ex['is_swallowed'] ? '[CAUGHT EXCEPTION (REDIRECTED)]' : '[UNHANDLED APPLICATION CRASH]';
                $log[] = "";
                $log[] = "============================================= {$header} ===";
                $log[] = "Class: {$ex['class']}";
                $log[] = "Message: {$ex['message']}";
                $log[] = "File: {$ex['file']}";
                $log[] = "Line: {$ex['line']}";
                
                if ($ex['blade_line_content']) {
                    $log[] = "";
                    $log[] = "============================================= [BLADE ENGINE SYNTAX CRASH] ======";
                    $log[] = "File: {$ex['file']}";
                    $log[] = "Line: {$ex['line']}";
                    $log[] = "Original Line: {$ex['blade_line_content']}";
                    $log[] = "Error: {$ex['message']}";
                }

                if (!empty($ex['trace'])) {
                    $log[] = "";
                    $log[] = "Callstack trace details:";
                    foreach ($ex['trace'] as $tr) {
                        $log[] = "  * {$tr}";
                    }
                }
            }
        }

        $log[] = str_repeat('=', 80);
        $log[] = "";
        
        return implode("\n", $log);
    }

    /**
     * Recursively traverses payloads to redact keys matching security patterns
     */
    protected function redactArray(array $data, array $customRedacts = []): array
    {
        if (!config('agent-debugger.recursive_redaction', true)) {
            return $data;
        }

        $redactKeys = array_merge([
            'password', 'password_confirmation', 'token', 'cvv', 'card', 'secret',
            'key', 'authorization', 'ssn', 'api_key', 'stripe_key', 'secret_token',
        ], $customRedacts);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactArray($value, $customRedacts);
            } else {
                foreach ($redactKeys as $redact) {
                    if (str_contains(strtolower((string)$key), $redact)) {
                        $data[$key] = '[REDACTED]';
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Truncates massive collections, retaining structural schema context samples
     */
    protected function truncateArrayPayload(array $data): array
    {
        $limit = config('agent-debugger.payload_sample_size', 2);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) > 5) {
                    $total = count($value);
                    $slice = array_slice($value, 0, $limit, true);
                    $slice['...'] = "[truncated " . ($total - $limit) . " more items; total collection count: {$total}]";
                    $data[$key] = $slice;
                } else {
                    $data[$key] = $this->truncateArrayPayload($value);
                }
            }
        }

        return $data;
    }

    /**
     * Flushes formatting streams to daily date-wise log paths
     */
    protected function writeLogToFile(string $content): void
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');

        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $fileName = $logStyle === 'single'
            ? 'agent_debug.log'
            : 'agent_debug-' . date('Y-m-d') . '.log';

        $filePath = $logPath . '/' . $fileName;

        // Auto-rotation of massive log files exceeding 20MB
        if (file_exists($filePath) && filesize($filePath) > 20 * 1024 * 1024) {
            $backupPath = $filePath . '.' . time() . '.bak';
            rename($filePath, $backupPath);
        }

        file_put_contents($filePath, $content, FILE_APPEND);
    }
}
