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

    public function addEvent(string $eventName, ?array $payload = null): void
    {
        $this->events[] = [
            'name' => $eventName,
            'payload' => $payload
        ];
    }

    public function addJob(string $jobName, string $queue, ?array $payload = null): void
    {
        $this->jobs[] = [
            'name' => $jobName,
            'queue' => $queue,
            'payload' => $payload
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

    protected array $eloquentEvents = [];

    public function addEloquentEvent(array $event): void
    {
        $this->eloquentEvents[] = $event;
    }

    public function getEloquentEvents(): array
    {
        return $this->eloquentEvents;
    }

    protected array $environmentWarnings = [];

    public function auditEnvironmentServices(): void
    {
        // 1. Check Redis queue connection
        if (config('queue.default') === 'redis') {
            $host = config('database.redis.default.host', '127.0.0.1');
            $port = (int)config('database.redis.default.port', 6379);
            if (!$this->isPortListening($host, $port)) {
                $this->environmentWarnings[] = [
                    'service' => 'Redis (Queue)',
                    'message' => "Redis host '{$host}:{$port}' is unreachable but QUEUE_CONNECTION is set to 'redis'."
                ];
            }
        }

        // 2. Check SMTP mail connection
        if (config('mail.default') === 'smtp') {
            $host = config('mail.mailers.smtp.host', '127.0.0.1');
            $port = (int)config('mail.mailers.smtp.port', 1025);
            if (!$this->isPortListening($host, $port)) {
                $this->environmentWarnings[] = [
                    'service' => 'SMTP (Mailer)',
                    'message' => "SMTP mailer '{$host}:{$port}' is offline but MAIL_MAILER is configured to 'smtp'."
                ];
            }
        }
    }

    protected function isPortListening(string $host, int $port): bool
    {
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                return true;
            }
        } catch (\Throwable $e) {
            // Ignore socket exceptions
        }
        return false;
    }

    public function getEnvironmentWarnings(): array
    {
        return $this->environmentWarnings;
    }

    protected array $csrfState = [];

    public function setCsrfState(array $state): void
    {
        $this->csrfState = $state;
    }

    public function getCsrfState(): array
    {
        return $this->csrfState;
    }

    protected array $envDrifts = [];

    public function auditEnvFile(): void
    {
        $basePath = base_path();
        $envPath = $basePath . '/.env';
        $examplePath = $basePath . '/.env.example';

        if (!file_exists($envPath) || !file_exists($examplePath)) {
            return;
        }

        $envKeys = $this->parseEnvKeys($envPath);
        $exampleKeys = $this->parseEnvKeys($examplePath);

        foreach ($exampleKeys as $key) {
            if (!in_array($key, $envKeys, true)) {
                $this->envDrifts[] = [
                    'key' => $key,
                    'status' => 'MISSING',
                    'message' => "Key '{$key}' is declared in .env.example but missing from your local .env file."
                ];
            }
        }
    }

    protected function parseEnvKeys(string $path): array
    {
        $keys = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            if (!empty($key)) {
                $keys[] = $key;
            }
        }
        return array_unique($keys);
    }

    public function getEnvDrifts(): array
    {
        return $this->envDrifts;
    }

    protected array $composerVulnerabilities = [];

    public function auditComposerDependencies(): void
    {
        $lockPath = base_path('composer.lock');
        if (!file_exists($lockPath)) {
            return;
        }

        $cacheKey = 'agent_debugger_composer_vulnerabilities';
        if (function_exists('cache') && cache()->has($cacheKey)) {
            $this->composerVulnerabilities = cache()->get($cacheKey) ?: [];
            return;
        }

        $vulnerabilities = [];
        try {
            $lockData = json_decode(file_get_contents($lockPath), true);
            $packages = array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []);

            $advisories = [
                'guzzlehttp/guzzle' => [
                    ['version' => '<7.4.5', 'cve' => 'CVE-2022-31090', 'title' => 'Request injection vulnerability in Guzzle'],
                    ['version' => '<6.5.8', 'cve' => 'CVE-2022-31091', 'title' => 'Failure to strip Authorization header on redirect']
                ],
                'laravel/framework' => [
                    ['version' => '<9.19.0', 'cve' => 'CVE-2022-31279', 'title' => 'Potential object injection via Cookie deserialization'],
                    ['version' => '<10.15.0', 'cve' => 'CVE-2023-38408', 'title' => 'Session hijacking vulnerability']
                ],
                'symfony/http-foundation' => [
                    ['version' => '<5.4.20', 'cve' => 'CVE-2022-42914', 'title' => 'Denial of service in Symfony HttpFoundation']
                ]
            ];

            foreach ($packages as $pkg) {
                $name = $pkg['name'] ?? '';
                $version = ltrim($pkg['version'] ?? '', 'v');

                if (isset($advisories[$name])) {
                    foreach ($advisories[$name] as $adv) {
                        $rule = $adv['version'];
                        $limitVer = ltrim($rule, '<>= ');
                        if (version_compare($version, $limitVer, '<')) {
                            $vulnerabilities[] = [
                                'package' => $name,
                                'installed' => $version,
                                'cve' => $adv['cve'],
                                'title' => $adv['title'],
                                'recommendation' => "Run `composer update {$name}` to patch this package."
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if (function_exists('cache')) {
            cache()->put($cacheKey, $vulnerabilities, 3600);
        }
        $this->composerVulnerabilities = $vulnerabilities;
    }

    public function getComposerVulnerabilities(): array
    {
        return $this->composerVulnerabilities;
    }

    protected array $localizationInfo = [];

    public function profileLocalization(\Illuminate\Http\Request $request): void
    {
        $locales = $request->getLanguages();
        $this->localizationInfo = [
            'locales' => $locales,
            'primary_locale' => !empty($locales) ? $locales[0] : 'en',
            'accept_language' => $request->header('Accept-Language', 'N/A'),
            'user_agent' => $request->header('User-Agent', 'Unknown'),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'timezone' => config('app.timezone', 'UTC')
        ];
    }

    public function getLocalizationInfo(): array
    {
        return $this->localizationInfo;
    }
}
