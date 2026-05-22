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
use LaravelAgentDebugger\Listeners\CacheProfiler;
use LaravelAgentDebugger\Listeners\EloquentProfiler;
use LaravelAgentDebugger\Listeners\MailProfiler;
use LaravelAgentDebugger\Services\LogCompiler;
use LaravelAgentDebugger\Services\SystemAuditor;
use LaravelAgentDebugger\Services\PayloadProcessor;
use LaravelAgentDebugger\Services\WebhookDispatcher;

/**
 * Middleware to capture telemetry metrics and route profiles during the request lifecycle.
 */
class DebugActivityLogger
{
    protected SessionBreadcrumbs $breadcrumbs;
    protected QueryProfiler $queryProfiler;
    protected ViewProfiler $viewProfiler;
    protected ExceptionProfiler $exceptionProfiler;
    protected EventJobProfiler $eventJobProfiler;
    protected HttpClientProfiler $httpClientProfiler;
    protected AuthorizationProfiler $authorizationProfiler;
    protected SessionStateProfiler $sessionStateProfiler;
    protected EnvironmentTracker $environmentTracker;
    protected CacheProfiler $cacheProfiler;
    protected EloquentProfiler $eloquentProfiler;
    protected MailProfiler $mailProfiler;

    public function __construct(
        protected readonly DebugLoggerManager $manager,
        protected readonly LogCompiler $logCompiler,
        protected readonly SystemAuditor $systemAuditor,
        protected readonly PayloadProcessor $payloadProcessor,
        protected readonly WebhookDispatcher $webhookDispatcher
    ) {
        $this->breadcrumbs = new SessionBreadcrumbs();
        $this->queryProfiler = new QueryProfiler($manager);
        $this->viewProfiler = new ViewProfiler($manager);
        $this->exceptionProfiler = new ExceptionProfiler($manager);
        $this->eventJobProfiler = new EventJobProfiler($manager);
        $this->httpClientProfiler = new HttpClientProfiler($manager);
        $this->authorizationProfiler = new AuthorizationProfiler($manager);
        $this->sessionStateProfiler = new SessionStateProfiler($manager);
        $this->environmentTracker = new EnvironmentTracker($manager);
        $this->cacheProfiler = new CacheProfiler($manager);
        $this->eloquentProfiler = new EloquentProfiler($manager);
        $this->mailProfiler = new MailProfiler($manager);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $this->breadcrumbs->push($request);

        $this->queryProfiler->subscribe();
        $this->viewProfiler->subscribe();
        $this->exceptionProfiler->subscribe();
        $this->eventJobProfiler->subscribe();
        $this->httpClientProfiler->subscribe();
        $this->authorizationProfiler->subscribe();
        $this->cacheProfiler->subscribe();
        $this->eloquentProfiler->subscribe();
        $this->mailProfiler->subscribe();

        $this->manager->setEnvironmentWarnings($this->systemAuditor->auditEnvironmentServices());
        $this->manager->setEnvDrifts($this->systemAuditor->auditEnvFile());
        $this->manager->setComposerVulnerabilities($this->systemAuditor->auditComposerDependencies());
        $this->manager->setLocalizationInfo($this->systemAuditor->profileLocalization($request));
        $this->manager->setGitInfo($this->systemAuditor->getGitInfo());

        $this->resolveAuthenticatedUser();

        try {
            $response = $next($request);
            $this->breadcrumbs->updateLastResponseStatus($response->getStatusCode());
            return $response;
        } catch (\Throwable $throwable) {
            $this->exceptionProfiler->logThrowable($throwable, false);
            $this->breadcrumbs->updateLastResponseStatus(500);
            throw $throwable;
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $this->sessionStateProfiler->profile($request);
        $this->environmentTracker->monitor();

        $inertiaMeta = $this->payloadProcessor->processInertiaPayload($request, $response);
        $livewireMeta = $this->payloadProcessor->processLivewirePayload($request, $response);

        $logOutput = $this->logCompiler->compileLogOutput($request, $response, $this->manager, $inertiaMeta, $livewireMeta);
        $this->writeLogToFile($logOutput);

        if ($response->getStatusCode() >= 500) {
            $this->webhookDispatcher->dispatch($request, $response, $this->manager->getExceptions());
        }
    }

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

    protected function resolveAuthenticatedUser(): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $id = $user->getAuthIdentifier();
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

    protected function writeLogToFile(string $content): void
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');

        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $fileName = $logStyle === 'single' ? 'agent_debug.log' : 'agent_debug-' . date('Y-m-d') . '.log';
        $filePath = $logPath . '/' . $fileName;

        if (file_exists($filePath) && filesize($filePath) > 20 * 1024 * 1024) {
            rename($filePath, $filePath . '.' . time() . '.bak');
        }

        file_put_contents($filePath, $content, FILE_APPEND);
    }
}
