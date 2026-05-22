<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

/**
 * Service to parse individual telemetry blocks using regular expressions.
 */
class BlockParser
{
    /**
     * Parse a single request block.
     *
     * @return array<string, mixed>
     */
    public static function parseBlock(string $block): array
    {
        $info = self::parseFrontMatter($block);
        $info['inertia_data'] = self::parseInertia($block);
        $info['livewire_data'] = self::parseLivewire($block);
        $info['cache_actions'] = self::parseCacheActions($block);
        $info['eloquent_events'] = self::parseEloquentEvents($block);
        $info['env_warnings'] = self::parseEnvWarnings($block);
        $info['env_drifts'] = self::parseEnvDrifts($block);
        $info['localization'] = self::parseLocalization($block);
        $info['composer_vulnerabilities'] = self::parseComposer($block);
        $info['redundant_queries'] = self::parseRedundantQueries($block);
        $info['index_advice'] = self::parseIndexAdvice($block);
        $info['queries_list'] = self::parseQueriesList($block);
        $info['emails'] = self::parseEmails($block);
        
        $sideEffects = self::parseSideEffects($block);
        $info['events'] = $sideEffects['events'];
        $info['jobs'] = $sideEffects['jobs'];
        
        $info['spans'] = self::parseSpans($block);
        $info['http_requests'] = self::parseHttpRequests($block);
        $info['views'] = self::parseViews($block);
        $info['memory_profile'] = self::parseMemoryProfile($block);
        $info['raw'] = $block;

        return $info;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parseFrontMatter(string $block): array
    {
        $csrfReason = null;
        if (preg_match('/csrf_reason:\s*(.*?)(?=\n|$)/', $block, $m)) {
            $reasonVal = trim($m[1], " \t\n\r\0\x0B\"");
            $csrfReason = $reasonVal !== 'null' ? $reasonVal : null;
        }

        return [
            'timestamp' => preg_match('/timestamp:\s*"(.*?)"/', $block, $m) ? $m[1] : '',
            'method' => preg_match('/method:\s*"(.*?)"/', $block, $m) ? $m[1] : 'GET',
            'url' => preg_match('/url:\s*"(.*?)"/', $block, $m) ? $m[1] : '',
            'status' => preg_match('/status:\s*(\d+)/', $block, $m) ? (int)$m[1] : 200,
            'duration' => preg_match('/execution_time_ms:\s*([\d\.]+)/', $block, $m) ? (float)$m[1] : 0.0,
            'memory' => preg_match('/memory_peak_mb:\s*([\d\.]+)/', $block, $m) ? (float)$m[1] : 0.0,
            'crashed' => preg_match('/crashed:\s*(true|false)/', $block, $m) && $m[1] === 'true',
            'queries_count' => preg_match('/queries_count:\s*(\d+)/', $block, $m) ? (int)$m[1] : 0,
            'errors_count' => preg_match('/errors_count:\s*(\d+)/', $block, $m) ? (int)$m[1] : 0,
            'cache_actions_count' => preg_match('/cache_actions_count:\s*(\d+)/', $block, $m) ? (int)$m[1] : 0,
            'eloquent_events_count' => preg_match('/eloquent_events_count:\s*(\d+)/', $block, $m) ? (int)$m[1] : 0,
            'csrf_checked' => preg_match('/csrf_checked:\s*(true|false)/', $block, $m) && $m[1] === 'true',
            'csrf_passed' => preg_match('/csrf_passed:\s*(true|false)/', $block, $m) && $m[1] === 'true',
            'csrf_reason' => $csrfReason,
            'debug_tag' => preg_match('/debug_tag:\s*"(.*?)"/', $block, $m) ? $m[1] : 'default',
            'git_branch' => preg_match('/git_branch:\s*"(.*?)"/', $block, $m) ? $m[1] : 'N/A',
            'git_changed_files' => preg_match('/git_changed_files:\s*(\d+)/', $block, $m) ? (int)$m[1] : 0,
        ];
    }

    protected static function parseInertia(string $block): ?array
    {
        if (preg_match('/Payload Link:\s*(file:\/\/.*?\.json)/', $block, $m)) {
            $realPath = str_replace('file://', '', $m[1]);
            if (file_exists($realPath)) {
                return json_decode((string)file_get_contents($realPath), true);
            }
        }
        return null;
    }

    protected static function parseLivewire(string $block): ?array
    {
        if (preg_match('/- LIVEWIRE STATE DUMP:\s*\* Component:\s*(.*?)\s*\* Payload Link:\s*(file:\/\/.*?\.json)/', $block, $m)) {
            $realPath = str_replace('file://', '', $m[2]);
            if (file_exists($realPath)) {
                return [
                    'component' => $m[1],
                    'payload' => (array)json_decode((string)file_get_contents($realPath), true)
                ];
            }
        }
        return null;
    }

    protected static function parseCacheActions(string $block): array
    {
        $actions = [];
        if (preg_match('/- CACHE ACTIONS:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[(.*?)\]\s*Key:\s*\'(.*?)\'(?:\s*\((\d+)\s*bytes\))?(?:\s*\[TTL:\s*(\d+)s\])?/', $trimmed, $lm)) {
                    $actions[] = [
                        'type' => $lm[1],
                        'key' => $lm[2],
                        'size' => isset($lm[3]) ? (int)$lm[3] : null,
                        'ttl' => isset($lm[4]) ? (int)$lm[4] : null,
                    ];
                }
            }
        }
        return $actions;
    }

    protected static function parseEloquentEvents(string $block): array
    {
        $events = [];
        if (preg_match('/- ELOQUENT MODEL LIFECYCLE EVENTS:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[Model Hook:\s*(.*?)\]\s*(.*?)(?:\s*\(ID:\s*(.*?)\))?$/', $trimmed, $lm)) {
                    $events[] = [
                        'event' => $lm[1],
                        'model' => $lm[2],
                        'id' => $lm[3] ?? null
                    ];
                }
            }
        }
        return $events;
    }

    protected static function parseEnvWarnings(string $block): array
    {
        $warnings = [];
        if (preg_match('/🚨 CONFIGURATION SHIELD - ACTIVE LOCAL SERVICES OFFLINE:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[(.*?)\]\s*(.*)/', $trimmed, $lm)) {
                    $warnings[] = ['service' => $lm[1], 'message' => $lm[2]];
                }
            }
        }
        return $warnings;
    }

    protected static function parseEnvDrifts(string $block): array
    {
        $drifts = [];
        if (preg_match('/⚖️ \.ENV FILE DRIFTS DETECTED \(MISSING KEYS\):\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[(.*?)\]\s*(.*)/', $trimmed, $lm)) {
                    $drifts[] = ['status' => $lm[1], 'message' => $lm[2]];
                }
            }
        }
        return $drifts;
    }

    protected static function parseLocalization(string $block): ?array
    {
        if (preg_match('/🌍 REQUEST LOCALIZATION & LANGUAGE PROFILE:\s*\* Primary Locale:\s*(.*?)\s*\* Accept-Language:\s*(.*?)\s*\* User-Agent:\s*(.*?)\s*\* Client IP Address:\s*(.*?)\s*\* App Timezone:\s*(.*?)(?=\n|$)/', $block, $m)) {
            return [
                'primary_locale' => $m[1],
                'accept_language' => $m[2],
                'user_agent' => $m[3],
                'ip_address' => $m[4],
                'timezone' => $m[5]
            ];
        }
        return null;
    }

    protected static function parseComposer(string $block): array
    {
        $vulns = [];
        if (preg_match('/🩹 COMPOSER SECURITY DEPENDENCY ADVISORIES:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            $current = null;
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[(.*?)\]\s*Package\s*\'(.*?)\'\s*\(Installed:\s*(.*?)\)\s*is vulnerable to:\s*\'(.*?)\'/', $trimmed, $lm)) {
                    if ($current) $vulns[] = $current;
                    $current = ['cve' => $lm[1], 'package' => $lm[2], 'installed' => $lm[3], 'title' => $lm[4], 'recommendation' => ''];
                } elseif (preg_match('/👉 Recommendation:\s*(.*)/', $trimmed, $lm)) {
                    if ($current) $current['recommendation'] = $lm[1];
                }
            }
            if ($current) $vulns[] = $current;
        }
        return $vulns;
    }

    protected static function parseRedundantQueries(string $block): array
    {
        $redundant = [];
        if (preg_match_all('/⚠️ WARNING: Duplicate\/Redundant Query Detected! The following exact query executed (\d+) times:\s*\* (.*?)\s*👉 Solution:\s*(.*?)(?=\n|$)/', $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $redundant[] = ['count' => (int)$match[1], 'sql' => trim($match[2]), 'remedy' => trim($match[3])];
            }
        }
        return $redundant;
    }

    protected static function parseIndexAdvice(string $block): array
    {
        $advice = [];
        if (preg_match_all('/💡 DATABASE INDEX RECOMMENDATION:\s*\* Table:\s*\'(.*?)\'\s*\|\s*Column:\s*\'(.*?)\'\s*👉 Solution:\s*(.*?)(?=\n|$)/', $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $advice[] = ['table' => trim($match[1]), 'column' => trim($match[2]), 'recommendation' => trim($match[3])];
            }
        }
        return $advice;
    }

    protected static function parseQueriesList(string $block): array
    {
        $queries = [];
        if (preg_match_all('/^\s*\* (\[SLOW QUERY \(([\d\.]+)ms\)\]|\[([\d\.]+)ms\]) (.*?)(?: \(Fired at (.*?)\))?$/m', $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isSlow = str_contains($match[1], 'SLOW QUERY');
                $queries[] = [
                    'time' => $isSlow ? (float)$match[2] : (float)$match[3],
                    'is_slow' => $isSlow,
                    'sql' => trim($match[4]),
                    'fileLine' => !empty($match[5]) ? trim($match[5]) : null
                ];
            }
        }
        return $queries;
    }

    protected static function parseEmails(string $block): array
    {
        $emails = [];
        if (preg_match('/📧 OUTGOING MAIL SANDBOX:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            $current = null;
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[Mail Sent\]\s*Subject:\s*\'(.*?)\'\s*\|\s*To:\s*(.*?)\s*\|\s*From:\s*(.*)/', $trimmed, $lm)) {
                    if ($current) $emails[] = $current;
                    $current = ['subject' => $lm[1], 'to' => $lm[2], 'from' => $lm[3], 'body' => 'No body captured.', 'link' => null];
                } elseif (preg_match('/^\*\s*Payload Link:\s*(file:\/\/.*?\.html)/', $trimmed, $lm)) {
                    if ($current) {
                        $current['link'] = $lm[1];
                        $realPath = str_replace('file://', '', $lm[1]);
                        if (file_exists($realPath)) {
                            $current['body'] = (string)file_get_contents($realPath);
                        }
                    }
                }
            }
            if ($current) $emails[] = $current;
        }
        return $emails;
    }

    /**
     * @return array{events: array<int, mixed>, jobs: array<int, mixed>}
     */
    protected static function parseSideEffects(string $block): array
    {
        $events = [];
        $jobs = [];
        if (preg_match('/- SIDE-EFFECT EVENTS & JOBS:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[Event\]\s*([^\s\{]+)(?:\s*(\{.*\}))?$/', $trimmed, $lm)) {
                    $events[] = ['name' => $lm[1], 'payload' => isset($lm[2]) ? (array)json_decode($lm[2], true) : null];
                } elseif (preg_match('/^\*\s*\[Job\]\s*(.*?)\s*\(Dispatched to \'(.*?)\' queue\)(?:\s*(\{.*\}))?$/', $trimmed, $lm)) {
                    $jobs[] = ['name' => $lm[1], 'queue' => $lm[2], 'payload' => isset($lm[3]) ? (array)json_decode($lm[3], true) : null];
                }
            }
        }
        return ['events' => $events, 'jobs' => $jobs];
    }

    protected static function parseSpans(string $block): array
    {
        $spans = [];
        if (preg_match('/- PERFORMANCE SPANS:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[([\d\.]+)ms\]\s*(.*)/', $trimmed, $lm)) {
                    $spans[] = ['duration' => (float)$lm[1], 'name' => trim($lm[2])];
                }
            }
        }
        return $spans;
    }

    protected static function parseHttpRequests(string $block): array
    {
        $requests = [];
        if (preg_match('/- OUTGOING EXTERNAL HTTP REQUESTS:\s*((?:\s*\*.*?\n?)*)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*\[(.*?)\s*(\d+)\]\s*(.*?)\s*\(Duration:\s*([\d\.]+)ms\)/', $trimmed, $lm)) {
                    $requests[] = ['method' => $lm[1], 'status' => (int)$lm[2], 'url' => $lm[3], 'duration' => (float)$lm[4]];
                }
            }
        }
        return $requests;
    }

    protected static function parseViews(string $block): array
    {
        $views = [];
        if (preg_match('/-\s*VIEW COMPOSITIONS:\s*([\s\S]*?)(?=\n-\s+[A-Z]|={40,}|$)/', $block, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty($trimmed = trim($line))) continue;
                if (preg_match('/^\*\s*(.*)/', $trimmed, $lm)) {
                    $views[] = trim($lm[1]);
                }
            }
        }
        return $views;
    }

    protected static function parseMemoryProfile(string $block): ?array
    {
        if (preg_match('/-\s*MEMORY ALLOCATION PROFILE:\s*([\s\S]*?)(?=={40,}|$)/', $block, $m)) {
            $totalMb = 0.0;
            $currentMb = 0.0;
            $layers = [];
            foreach (explode("\n", trim($m[1])) as $ml) {
                if (empty($trimmed = trim($ml))) continue;
                if (preg_match('/^\*\s*Peak:\s*([\d.]+)\s*MB\s*\|\s*Current:\s*([\d.]+)\s*MB/', $trimmed, $pm)) {
                    $totalMb = (float)$pm[1];
                    $currentMb = (float)$pm[2];
                } elseif (preg_match('/^\*\s*\[(\d+)%\]\s*(.+?)\s+(.+?)\s+\(([\d.]+)\s*MB\)/', $trimmed, $lm)) {
                    $icon = mb_substr($lm[2], 0, 2, 'UTF-8');
                    $label = trim(mb_substr($lm[2], mb_strlen($icon, 'UTF-8'), null, 'UTF-8') . ' ' . $lm[3]);
                    $colorMap = ['🏛' => 'purple', '📦' => 'amber', '🧬' => 'cyan', '🔥' => 'rose'];
                    $layers[] = [
                        'pct' => (int)$lm[1],
                        'icon' => $icon,
                        'label' => trim($label),
                        'mb' => (float)$lm[4],
                        'color' => $colorMap[$icon] ?? 'slate',
                    ];
                }
            }
            if ($totalMb > 0) {
                return ['total_mb' => $totalMb, 'current_mb' => $currentMb, 'layers' => $layers];
            }
        }
        return null;
    }
}
