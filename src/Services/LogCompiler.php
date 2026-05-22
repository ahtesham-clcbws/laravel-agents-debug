<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use LaravelAgentDebugger\DebugLoggerManager;
use LaravelAgentDebugger\Breadcrumbs\SessionBreadcrumbs;

/**
 * Service to compile request details, telemetry data, and exceptions into a formatted string log block.
 */
class LogCompiler
{
    public function __construct(
        protected readonly QueryAnalyzer $queryAnalyzer
    ) {}

    /**
     * Compile telemetry dataset into a structured string block.
     *
     * @param array{component: string, url: string, file_link: string}|null $inertiaMeta
     * @param array{component: string, file_link: string}|null $livewireMeta
     */
    public function compileLogOutput(
        Request $request,
        Response $response,
        DebugLoggerManager $manager,
        ?array $inertiaMeta,
        ?array $livewireMeta
    ): string {
        $timestamp = date('Y-m-d H:i:s');
        $method = (string)$request->getMethod();
        $url = (string)$request->fullUrl();
        $ip = (string)($request->ip() ?? '127.0.0.1');
        
        $duration = (float)$manager->getExecutionTime();
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        $routeAction = $request->route() ? (string)$request->route()->getActionName() : 'N/A';
        $referer = (string)$request->header('referer', 'N/A');

        // Compile User Header Context
        $userString = 'Guest';
        $activeUser = $manager->getActiveUser();
        if ($activeUser) {
            $userString = "User #{$activeUser['id']} (" . strtolower($activeUser['class']) . ": {$activeUser['identifier']})";
        }

        $crashedSuffix = $response->getStatusCode() >= 500 ? ' (CRASHED)' : '';
        $statusCode = $response->getStatusCode() . $crashedSuffix;

        $isCrashed = $response->getStatusCode() >= 500;
        $queries = $manager->getQueries();
        $exceptions = $manager->getExceptions();
        
        $queriesCount = count($queries);
        $errorsCount = count($exceptions);
        $cacheActionsCount = count($manager->getCacheActions());
        $eloquentEventsCount = count($manager->getEloquentEvents());

        $csrfState = $manager->getCsrfState();
        $csrfChecked = ($csrfState['checked'] ?? false) ? 'true' : 'false';
        $csrfPassed = ($csrfState['passed'] ?? false) ? 'true' : 'false';
        $csrfReason = ($csrfState['reason'] ?? null) ? "\"{$csrfState['reason']}\"" : 'null';

        $debugTag = (string)$request->query('_debug_tag', 'default');

        $log = [];
        $log[] = str_repeat('=', 80);
        $log[] = "---";
        $log[] = "timestamp: \"{$timestamp}\"";
        $log[] = "method: \"{$method}\"";
        $log[] = "url: \"{$url}\"";
        $log[] = "status: " . $response->getStatusCode();
        $log[] = "execution_time_ms: {$duration}";
        $log[] = "memory_peak_mb: {$memory}";
        $log[] = "crashed: " . ($isCrashed ? 'true' : 'false');
        $log[] = "queries_count: {$queriesCount}";
        $log[] = "errors_count: {$errorsCount}";
        $log[] = "cache_actions_count: {$cacheActionsCount}";
        $log[] = "eloquent_events_count: {$eloquentEventsCount}";
        
        $gitInfo = $manager->getGitInfo();
        $gitBranch = $gitInfo ? (string)$gitInfo['branch'] : 'N/A';
        $gitChanged = $gitInfo ? (int)$gitInfo['changed_files'] : 0;

        $log[] = "csrf_checked: {$csrfChecked}";
        $log[] = "csrf_passed: {$csrfPassed}";
        $log[] = "csrf_reason: {$csrfReason}";
        $log[] = "debug_tag: \"{$debugTag}\"";
        $log[] = "git_branch: \"{$gitBranch}\"";
        $log[] = "git_changed_files: {$gitChanged}";
        $log[] = "---";
        $log[] = "[{$timestamp}] REQUEST: {$method} {$url}";
        $log[] = "IP: {$ip} | Auth: {$userString} | Execution: {$duration}ms | Memory Peak: {$memory} MB";
        $log[] = "Route Action: {$routeAction}";
        $log[] = "Referer: {$referer} | Response Status: {$statusCode}";

        // Config Drifts
        $drifts = $manager->getConfigDrifts();
        if (!empty($drifts)) {
            $log[] = "";
            $log[] = "⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!";
            foreach ($drifts as $key => $drift) {
                $log[] = "  - {$key} changed from '{$drift['old']}' to '{$drift['new']}'";
            }
        }

        // Configuration Shield Warnings
        $warnings = $manager->getEnvironmentWarnings();
        if (!empty($warnings)) {
            $log[] = "";
            $log[] = "🚨 CONFIGURATION SHIELD - ACTIVE LOCAL SERVICES OFFLINE:";
            foreach ($warnings as $w) {
                $log[] = "  * [{$w['service']}] {$w['message']}";
            }
        }

        // .env vs .env.example Audit Drifts
        $envDrifts = $manager->getEnvDrifts();
        if (!empty($envDrifts)) {
            $log[] = "";
            $log[] = "⚖️ .ENV FILE DRIFTS DETECTED (MISSING KEYS):";
            foreach ($envDrifts as $ed) {
                $log[] = "  * [{$ed['status']}] {$ed['message']}";
            }
        }

        // Request Localization Info
        $locInfo = $manager->getLocalizationInfo();
        if (!empty($locInfo)) {
            $log[] = "";
            $log[] = "🌍 REQUEST LOCALIZATION & LANGUAGE PROFILE:";
            $log[] = "  * Primary Locale: {$locInfo['primary_locale']}";
            $log[] = "  * Accept-Language: {$locInfo['accept_language']}";
            $log[] = "  * User-Agent: {$locInfo['user_agent']}";
            $log[] = "  * Client IP Address: {$locInfo['ip_address']}";
            $log[] = "  * App Timezone: {$locInfo['timezone']}";
        }

        // Git Code-Diff Correlation
        if ($gitInfo) {
            $log[] = "";
            $log[] = "🚀 GIT CODE CORRELATION DATA:";
            $log[] = "  * Active Branch: {$gitInfo['branch']}";
            $log[] = "  * Uncommitted Changes Count: {$gitInfo['changed_files']}";
        }

        // Composer Security Dependencies
        $composerVulns = $manager->getComposerVulnerabilities();
        if (!empty($composerVulns)) {
            $log[] = "";
            $log[] = "🩹 COMPOSER SECURITY DEPENDENCY ADVISORIES:";
            foreach ($composerVulns as $v) {
                $log[] = "  * [{$v['cve']}] Package '{$v['package']}' (Installed: {$v['installed']}) is vulnerable to: '{$v['title']}'";
                $log[] = "    👉 Recommendation: {$v['recommendation']}";
            }
        }

        // Outgoing Mail Sandbox
        $emails = $manager->getEmails();
        if (!empty($emails)) {
            $log[] = "";
            $log[] = "📧 OUTGOING MAIL SANDBOX:";
            foreach ($emails as $email) {
                $log[] = "  * [Mail Sent] Subject: '{$email['subject']}' | To: {$email['to']} | From: {$email['from']}";
                if ($email['link']) {
                    $log[] = "    * Payload Link: {$email['link']}";
                }
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
        $session = $manager->getSessionState();
        if (!empty($session)) {
            $log[] = "";
            $log[] = "- ACTIVE SESSION STATE:";
            foreach ($session as $key => $val) {
                $valStr = is_scalar($val) ? (string)$val : json_encode($val);
                $log[] = "  * {$key} => {$valStr}";
            }
        }

        // Authorization Gate Results
        $gates = $manager->getGates();
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

        // Process Inertia Properties
        if ($inertiaMeta) {
            $log[] = "";
            $log[] = "- INERTIA STATE DUMP:";
            $log[] = "  * Component: {$inertiaMeta['component']}";
            $log[] = "  * URL: {$inertiaMeta['url']}";
            $log[] = "  * Payload Link: {$inertiaMeta['file_link']}";
        }

        // Process Livewire Properties
        if ($livewireMeta) {
            $log[] = "";
            $log[] = "- LIVEWIRE STATE DUMP:";
            $log[] = "  * Component: {$livewireMeta['component']}";
            $log[] = "  * Payload Link: {$livewireMeta['file_link']}";
        }

        // SQL Database transaction actions and Queries
        $transactions = $manager->getTransactions();
        
        if (!empty($queries) || !empty($transactions)) {
            $log[] = "";
            $log[] = "- DATABASE TRANSACTION & QUERIES:";
            
            // Reconstruct interleaving sequences
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
            $repeats = $this->queryAnalyzer->getRepeatingQueries($queries);
            if (!empty($repeats)) {
                $log[] = "";
                foreach ($repeats as $rep) {
                    $log[] = "  ⚠️ WARNING: N+1 Query Detected! The following query executed {$rep['count']} times:";
                    $log[] = "  * {$rep['query']}";
                    $log[] = "  👉 Solution: {$rep['remedy']}";
                }
            }

            // Append Redundant exact query warnings
            $redundant = $this->queryAnalyzer->getRedundantQueries($queries);
            if (!empty($redundant)) {
                $log[] = "";
                foreach ($redundant as $red) {
                    $log[] = "  ⚠️ WARNING: Duplicate/Redundant Query Detected! The following exact query executed {$red['count']} times:";
                    $log[] = "  * {$red['sql']}";
                    $log[] = "  👉 Solution: {$red['remedy']}";
                }
            }

            // Append Query Index Advice
            $advice = $this->queryAnalyzer->getIndexAdvice($queries);
            if (!empty($advice)) {
                $log[] = "";
                foreach ($advice as $adv) {
                    $log[] = "  💡 DATABASE INDEX RECOMMENDATION:";
                    $log[] = "  * Table: '{$adv['table']}' | Column: '{$adv['column']}'";
                    $log[] = "  👉 Solution: {$adv['recommendation']}";
                }
            }
        }

        // View Compositions
        $views = $manager->getViews();
        if (!empty($views)) {
            $log[] = "";
            $log[] = "- VIEW COMPOSITIONS:";
            foreach ($views as $view) {
                $log[] = "  * {$view}";
            }
        }

        // Outgoing HTTP Requests
        $calls = $manager->getHttpCalls();
        if (!empty($calls)) {
            $log[] = "";
            $log[] = "- OUTGOING EXTERNAL HTTP REQUESTS:";
            foreach ($calls as $c) {
                $log[] = "  * [{$c['method']} {$c['status']}] {$c['url']} (Duration: {$c['duration']}ms)";
            }
        }

        // Performance Spans
        $spans = $manager->getSpans();
        if (!empty($spans)) {
            $log[] = "";
            $log[] = "- PERFORMANCE SPANS:";
            foreach ($spans as $s) {
                $log[] = "  * [{$s['duration']}ms] {$s['name']}";
            }
        }

        // Cache Actions
        $cacheActions = $manager->getCacheActions();
        if (!empty($cacheActions)) {
            $log[] = "";
            $log[] = "- CACHE ACTIONS:";
            foreach ($cacheActions as $action) {
                $sizeStr = $action['size'] !== null ? " ({$action['size']} bytes)" : '';
                $ttlStr = $action['ttl'] !== null ? " [TTL: {$action['ttl']}s]" : '';
                $log[] = "  * [{$action['type']}] Key: '{$action['key']}'{$sizeStr}{$ttlStr}";
            }
        }

        // Eloquent Model Lifecycle Events
        $eloquentEvents = $manager->getEloquentEvents();
        if (!empty($eloquentEvents)) {
            $log[] = "";
            $log[] = "- ELOQUENT MODEL LIFECYCLE EVENTS:";
            foreach ($eloquentEvents as $ee) {
                $idStr = $ee['id'] !== null ? " (ID: {$ee['id']})" : '';
                $log[] = "  * [Model Hook: {$ee['event']}] {$ee['model']}{$idStr}";
            }
        }

        // Events & Jobs dispatches
        $events = $manager->getEvents();
        $jobs = $manager->getJobs();
        
        if (!empty($events) || !empty($jobs)) {
            $log[] = "";
            $log[] = "- SIDE-EFFECT EVENTS & JOBS:";
            foreach ($events as $e) {
                if (is_array($e)) {
                    $payloadStr = $e['payload'] ? ' ' . json_encode($e['payload'], JSON_UNESCAPED_SLASHES) : '';
                    $log[] = "  * [Event] {$e['name']}{$payloadStr}";
                } else {
                    $log[] = "  * [Event] {$e}";
                }
            }
            foreach ($jobs as $j) {
                $payloadStr = (!empty($j['payload'])) ? ' ' . json_encode($j['payload'], JSON_UNESCAPED_SLASHES) : '';
                $log[] = "  * [Job] {$j['name']} (Dispatched to '{$j['queue']}' queue){$payloadStr}";
            }
        }

        // Blade Engine Crash / Swallow Exception Details
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

        // Memory Allocation Profile
        $memProfile = $manager->getMemoryProfile();
        $log[] = "";
        $log[] = "- MEMORY ALLOCATION PROFILE:";
        $log[] = "  * Peak: {$memProfile['total_mb']} MB | Current: {$memProfile['current_mb']} MB";
        foreach ($memProfile['layers'] as $layer) {
            $log[] = "  * [{$layer['pct']}%] {$layer['icon']} {$layer['label']} ({$layer['mb']} MB)";
        }

        $log[] = str_repeat('=', 80);
        $log[] = "";
        
        return implode("\n", $log);
    }

    /**
     * Recursively traverses payloads to redact keys matching security patterns.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $customRedacts
     * @return array<string, mixed>
     */
    public function redactArray(array $data, array $customRedacts = []): array
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
     * Truncates massive collections, retaining structural schema context samples.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function truncateArrayPayload(array $data): array
    {
        $limit = (int)config('agent-debugger.payload_sample_size', 2);

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
}
