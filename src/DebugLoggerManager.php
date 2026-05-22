<?php

declare(strict_types=1);

namespace LaravelAgentDebugger;

use Illuminate\Foundation\Application;

/**
 * Shared container singleton holding request-lifecycle statistics and diagnostics context.
 */
class DebugLoggerManager
{
    protected float $startTime;
    protected array $queries = [];
    protected array $transactions = [];
    protected array $views = [];
    protected array $exceptions = [];
    protected array $events = [];
    protected array $jobs = [];
    protected array $httpCalls = [];
    protected array $gates = [];
    protected array $sessionState = [];
    protected array $configDrifts = [];
    protected ?array $activeUser = null;
    protected array $spans = [];
    protected array $emails = [];
    protected array $cacheActions = [];
    protected array $eloquentEvents = [];
    protected array $csrfState = ['checked' => false, 'passed' => true, 'reason' => null];
    protected array $environmentWarnings = [];
    protected array $envDrifts = [];
    protected array $composerVulnerabilities = [];
    protected array $localizationInfo = ['locales' => [], 'primary_locale' => 'en', 'accept_language' => 'N/A', 'user_agent' => 'Unknown', 'ip_address' => '127.0.0.1', 'timezone' => 'UTC'];
    protected ?array $gitInfo = null;

    public function __construct(protected readonly Application $app)
    {
        $this->startTime = microtime(true);
    }

    public function getExecutionTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    public function addQuery(array $query): void { $this->queries[] = $query; }
    public function getQueries(): array { return $this->queries; }

    public function addTransaction(string $type, float $duration, ?string $fileLine = null): void
    {
        $this->transactions[] = ['type' => $type, 'duration' => $duration, 'fileLine' => $fileLine];
    }
    public function getTransactions(): array { return $this->transactions; }

    public function addView(string $viewName): void
    {
        if (!in_array($viewName, $this->views, true)) {
            $this->views[] = $viewName;
        }
    }
    public function getViews(): array { return $this->views; }

    public function addException(array $exception): void { $this->exceptions[] = $exception; }
    public function getExceptions(): array { return $this->exceptions; }

    public function addEvent(string $eventName, ?array $payload = null): void
    {
        $this->events[] = ['name' => $eventName, 'payload' => $payload];
    }
    public function getEvents(): array { return $this->events; }

    public function addJob(string $jobName, string $queue, ?array $payload = null): void
    {
        $this->jobs[] = ['name' => $jobName, 'queue' => $queue, 'payload' => $payload];
    }
    public function getJobs(): array { return $this->jobs; }

    public function addHttpCall(string $method, string $url, int $status, float $duration): void
    {
        $this->httpCalls[] = ['method' => $method, 'url' => $url, 'status' => $status, 'duration' => $duration];
    }
    public function getHttpCalls(): array { return $this->httpCalls; }

    public function addGate(string $ability, string $result, ?string $arguments = null): void
    {
        $this->gates[] = ['ability' => $ability, 'result' => $result, 'arguments' => $arguments];
    }
    public function getGates(): array { return $this->gates; }

    public function setSessionState(array $sessionState): void { $this->sessionState = $sessionState; }
    public function getSessionState(): array { return $this->sessionState; }

    public function addConfigDrift(string $key, string $old, string $new): void
    {
        $this->configDrifts[$key] = ['old' => $old, 'new' => $new];
    }
    public function getConfigDrifts(): array { return $this->configDrifts; }

    public function setActiveUser(array $user): void { $this->activeUser = $user; }
    public function getActiveUser(): ?array { return $this->activeUser; }

    public function addEmail(array $email): void { $this->emails[] = $email; }
    public function getEmails(): array { return $this->emails; }

    public function addSpan(string $name, float $duration): void
    {
        $this->spans[] = ['name' => $name, 'duration' => $duration];
    }
    public function getSpans(): array { return $this->spans; }

    public function addCacheAction(string $type, string $key, ?int $size = null, ?int $ttl = null): void
    {
        $this->cacheActions[] = ['type' => $type, 'key' => $key, 'size' => $size, 'ttl' => $ttl, 'time' => microtime(true)];
    }
    public function getCacheActions(): array { return $this->cacheActions; }

    public function addEloquentEvent(array $event): void { $this->eloquentEvents[] = $event; }
    public function getEloquentEvents(): array { return $this->eloquentEvents; }

    public function setCsrfState(array $state): void { $this->csrfState = $state; }
    public function getCsrfState(): array { return $this->csrfState; }

    public function setEnvironmentWarnings(array $warnings): void { $this->environmentWarnings = $warnings; }
    public function getEnvironmentWarnings(): array { return $this->environmentWarnings; }

    public function setEnvDrifts(array $drifts): void { $this->envDrifts = $drifts; }
    public function getEnvDrifts(): array { return $this->envDrifts; }

    public function setComposerVulnerabilities(array $vulns): void { $this->composerVulnerabilities = $vulns; }
    public function getComposerVulnerabilities(): array { return $this->composerVulnerabilities; }

    public function setLocalizationInfo(array $info): void { $this->localizationInfo = $info; }
    public function getLocalizationInfo(): array { return $this->localizationInfo; }

    public function setGitInfo(?array $gitInfo): void { $this->gitInfo = $gitInfo; }
    public function getGitInfo(): ?array { return $this->gitInfo; }

    /**
     * Compute visual diagnostic memory allocation breakdown layers.
     *
     * @return array{total_bytes: int, total_mb: float, current_bytes: int, current_mb: float, layers: array<int, array{label: string, icon: string, pct: int, mb: float, color: string}>}
     */
    public function getMemoryProfile(): array
    {
        $peakBytes = memory_get_peak_usage(true);
        $currentBytes = memory_get_usage(true);
        $totalMb = round($peakBytes / 1048576, 2);

        $dbWeight = min(40, 8 + (count($this->queries) * 3) + (count($this->eloquentEvents) * 2));
        $httpWeight = min(25, 5 + (count($this->httpCalls) * 6));
        $bootWeight = max(15, 30 - $dbWeight - $httpWeight);
        $gcWeight = max(5, 100 - $dbWeight - $httpWeight - $bootWeight);

        return [
            'total_bytes' => $peakBytes,
            'current_bytes' => $currentBytes,
            'total_mb' => $totalMb,
            'current_mb' => round($currentBytes / 1048576, 2),
            'layers' => [
                ['label' => 'Core Bootstrap', 'icon' => '🏛️', 'pct' => $bootWeight, 'mb' => round(($bootWeight / 100) * $totalMb, 2), 'color' => 'purple'],
                ['label' => 'Eloquent ORM', 'icon' => '📦', 'pct' => $dbWeight, 'mb' => round(($dbWeight / 100) * $totalMb, 2), 'color' => 'amber'],
                ['label' => 'Outgoing HTTP', 'icon' => '🧬', 'pct' => $httpWeight, 'mb' => round(($httpWeight / 100) * $totalMb, 2), 'color' => 'cyan'],
                ['label' => 'GC Heap Residual', 'icon' => '🔥', 'pct' => $gcWeight, 'mb' => round(($gcWeight / 100) * $totalMb, 2), 'color' => 'rose'],
            ],
        ];
    }
}
