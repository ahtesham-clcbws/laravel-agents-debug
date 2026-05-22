<?php

declare(strict_types=1);

namespace LaravelAgentDebugger;

use Illuminate\Foundation\Application;

class DebugLoggerManager
{
    protected Application $app;

    // Log storage variables for request context
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

    /**
     * Constructor promotion mapping Laravel container
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->startTime = microtime(true);
    }

    /**
     * Start capturing time parameters
     */
    public function getExecutionTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    // Setters for storing collected parameters in memory
    public function addQuery(array $query): void
    {
        $this->queries[] = $query;
    }

    public function addTransaction(string $type, float $duration, ?string $fileLine = null): void
    {
        $this->transactions[] = [
            'type' => $type,
            'duration' => $duration,
            'fileLine' => $fileLine,
        ];
    }

    public function addView(string $viewName): void
    {
        if (!in_array($viewName, $this->views, true)) {
            $this->views[] = $viewName;
        }
    }

    public function addException(array $exception): void
    {
        $this->exceptions[] = $exception;
    }

    public function addEvent(string $eventName): void
    {
        $this->events[] = $eventName;
    }

    public function addJob(string $jobName, string $queue): void
    {
        $this->jobs[] = [
            'name' => $jobName,
            'queue' => $queue,
        ];
    }

    public function addHttpCall(string $method, string $url, int $status, float $duration): void
    {
        $this->httpCalls[] = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'duration' => $duration,
        ];
    }

    public function addGate(string $ability, string $result, ?string $arguments = null): void
    {
        $this->gates[] = [
            'ability' => $ability,
            'result' => $result,
            'arguments' => $arguments,
        ];
    }

    public function setSessionState(array $sessionState): void
    {
        $this->sessionState = $sessionState;
    }

    public function addConfigDrift(string $key, string $old, string $new): void
    {
        $this->configDrifts[$key] = [
            'old' => $old,
            'new' => $new,
        ];
    }

    public function setActiveUser(array $user): void
    {
        $this->activeUser = $user;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getViews(): array
    {
        return $this->views;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function getHttpCalls(): array
    {
        return $this->httpCalls;
    }

    public function getGates(): array
    {
        return $this->gates;
    }

    public function getSessionState(): array
    {
        return $this->sessionState;
    }

    public function getConfigDrifts(): array
    {
        return $this->configDrifts;
    }

    public function getActiveUser(): ?array
    {
        return $this->activeUser;
    }

    public function addSpan(string $name, float $duration): void
    {
        $this->spans[] = [
            'name' => $name,
            'duration' => $duration,
        ];
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    protected array $cacheActions = [];

    public function addCacheAction(string $type, string $key, ?int $size = null, ?int $ttl = null): void
    {
        $this->cacheActions[] = [
            'type' => $type,
            'key' => $key,
            'size' => $size,
            'ttl' => $ttl,
            'time' => microtime(true)
        ];
    }

    public function getCacheActions(): array
    {
        return $this->cacheActions;
    }
}
