<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

use Illuminate\Http\Request;

/**
 * Service to audit environment status and security.
 */
class SystemAuditor
{
    /**
     * Audit configuration systems like Redis and SMTP.
     */
    public function auditEnvironmentServices(): array
    {
        $warnings = [];

        if (config('queue.default') === 'redis') {
            $host = (string)config('database.redis.default.host', '127.0.0.1');
            $port = (int)config('database.redis.default.port', 6379);
            if (!$this->isPortListening($host, $port)) {
                $warnings[] = ['service' => 'Redis (Queue)', 'message' => "Redis host '{$host}:{$port}' unreachable."];
            }
        }

        if (config('mail.default') === 'smtp') {
            $host = (string)config('mail.mailers.smtp.host', '127.0.0.1');
            $port = (int)config('mail.mailers.smtp.port', 1025);
            if (!$this->isPortListening($host, $port)) {
                $warnings[] = ['service' => 'SMTP (Mailer)', 'message' => "SMTP mailer '{$host}:{$port}' offline."];
            }
        }

        return $warnings;
    }

    protected function isPortListening(string $host, int $port): bool
    {
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                return true;
            }
        } catch (\Throwable) {}
        return false;
    }

    /**
     * Audit local .env keys against .env.example.
     */
    public function auditEnvFile(): array
    {
        $drifts = [];
        $env = $this->parseEnvKeys(base_path('/.env'));
        $example = $this->parseEnvKeys(base_path('/.env.example'));

        foreach ($example as $key) {
            if (!in_array($key, $env, true)) {
                $drifts[] = ['key' => $key, 'status' => 'MISSING', 'message' => "Key '{$key}' missing."];
            }
        }
        return $drifts;
    }

    protected function parseEnvKeys(string $path): array
    {
        $keys = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (empty($l = trim($line)) || str_starts_with($l, '#')) continue;
            $parts = explode('=', $l, 2);
            if (!empty($key = trim($parts[0]))) $keys[] = $key;
        }
        return array_unique($keys);
    }

    /**
     * Perform composer.lock security advisory search.
     */
    public function auditComposerDependencies(): array
    {
        $lockPath = base_path('composer.lock');
        if (!file_exists($lockPath)) return [];

        $cacheKey = 'agent_debugger_composer_vulnerabilities';
        if (function_exists('cache') && cache()->has($cacheKey)) return (array)cache()->get($cacheKey) ?: [];

        $vulnerabilities = [];
        try {
            $lockData = json_decode((string)file_get_contents($lockPath), true);
            $packages = array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []);
            $advisories = [
                'guzzlehttp/guzzle' => [['version' => '<7.4.5', 'cve' => 'CVE-2022-31090', 'title' => 'Request injection']],
                'laravel/framework' => [['version' => '<9.19.0', 'cve' => 'CVE-2022-31279', 'title' => 'Object injection']],
                'symfony/http-foundation' => [['version' => '<5.4.20', 'cve' => 'CVE-2022-42914', 'title' => 'DoS']]
            ];

            foreach ($packages as $pkg) {
                $name = (string)($pkg['name'] ?? '');
                $version = ltrim((string)($pkg['version'] ?? ''), 'v');
                if (isset($advisories[$name])) {
                    foreach ($advisories[$name] as $adv) {
                        if (version_compare($version, ltrim($adv['version'], '<>= '), '<')) {
                            $vulnerabilities[] = ['package' => $name, 'installed' => $version, 'cve' => $adv['cve'], 'title' => $adv['title'], 'recommendation' => "Run `composer update {$name}`."];
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        if (function_exists('cache')) cache()->put($cacheKey, $vulnerabilities, 3600);
        return $vulnerabilities;
    }

    /**
     * Resolve request localization metrics.
     */
    public function profileLocalization(Request $request): array
    {
        $locales = $request->getLanguages();
        return [
            'locales' => $locales,
            'primary_locale' => !empty($locales) ? $locales[0] : 'en',
            'accept_language' => (string)$request->header('Accept-Language', 'N/A'),
            'user_agent' => (string)$request->header('User-Agent', 'Unknown'),
            'ip_address' => (string)($request->ip() ?? '127.0.0.1'),
            'timezone' => (string)config('app.timezone', 'UTC')
        ];
    }

    /**
     * Extract git branch name and status.
     */
    public function getGitInfo(): ?array
    {
        if (!is_dir(base_path('/.git'))) return null;
        $branch = 'N/A'; $changed = 0;
        try {
            $head = trim((string)file_get_contents(base_path('/.git/HEAD')));
            if (str_starts_with($head, 'ref:')) {
                $parts = explode('/', $head);
                $branch = (string)end($parts);
            } else {
                $branch = substr($head, 0, 7);
            }
            if (function_exists('shell_exec')) {
                $status = shell_exec('git status --porcelain 2>/dev/null');
                if ($status !== null) {
                    $lines = array_filter(explode("\n", trim($status)));
                    $changed = count($lines);
                }
            }
        } catch (\Throwable) {}

        return [
            'branch' => $branch,
            'changed_files' => $changed
        ];
    }
}
