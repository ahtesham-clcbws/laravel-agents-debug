<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use LaravelAgentDebugger\DebugLoggerManager;

class QueryProfiler
{
    protected DebugLoggerManager $manager;
    protected array $queryCounts = [];
    protected array $templatedQueries = [];

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Boot listeners mapping database operations
     */
    public function subscribe(): void
    {
        // Listen to standard queries execution
        DB::listen(function (QueryExecuted $event) {
            $this->logQuery($event);
        });

        // Listen to begins, commits, and rollbacks
        DB::connection()->getEventDispatcher()->listen(TransactionBeginning::class, function () {
            $this->logTransaction('DB::beginTransaction()');
        });

        DB::connection()->getEventDispatcher()->listen(TransactionCommitted::class, function () {
            $this->logTransaction('DB::commit()');
        });

        DB::connection()->getEventDispatcher()->listen(TransactionRolledBack::class, function () {
            $this->logTransaction('DB::rollBack()');
        });
    }

    /**
     * Format and record bound database SQL statements
     */
    protected function logQuery(QueryExecuted $event): void
    {
        $sql = $event->sql;
        $bindings = $event->bindings;
        $time = $event->time; // in milliseconds

        // Replace SQL bindings to reconstruct bound query text
        if (!empty($bindings)) {
            foreach ($bindings as $binding) {
                $value = is_string($binding) ? "'" . addslashes($binding) . "'" : $binding;
                if (is_null($value)) {
                    $value = 'NULL';
                }
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $sql = substr_replace($sql, (string)$value, $pos, 1);
                }
            }
        }

        $slowThreshold = config('agent-debugger.slow_query_threshold', 10.0);
        $isSlow = $slowThreshold > 0 && $time >= $slowThreshold;

        $fileLine = null;
        if (config('agent-debugger.log_query_source', true)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            foreach ($trace as $step) {
                if (isset($step['file']) && !str_contains($step['file'], 'vendor/') && !str_contains($step['file'], 'laravel-agents-debug') && !str_contains($step['file'], 'Local_Debug_Activity')) {
                    $fileLine = basename($step['file']) . ' line ' . ($step['line'] ?? 0);
                    break;
                }
            }
        }

        $this->manager->addQuery([
            'sql' => $sql,
            'time' => $time,
            'is_slow' => $isSlow,
            'fileLine' => $fileLine,
        ]);

        // Process query for N+1 loop detection
        if (config('agent-debugger.detect_n_plus_one', true)) {
            $this->detectNPlusOne($event->sql);
        }
    }

    /**
     * Map transaction actions back to standard controller files
     */
    protected function logTransaction(string $type): void
    {
        $fileLine = null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        // Scan stack to locate developer-allocated file
        foreach ($trace as $step) {
            if (isset($step['file']) && !str_contains($step['file'], 'vendor/')) {
                $fileLine = basename($step['file']) . ' line ' . ($step['line'] ?? 0);
                break;
            }
        }

        $this->manager->addTransaction($type, 0.0, $fileLine);
    }

    /**
     * Check query signatures, counting iterations to detect loops
     */
    protected function detectNPlusOne(string $rawSql): void
    {
        // Replace dynamic parameters with wildcards to create signature
        $signature = preg_replace(
            ['/\'[^\']+\'/', '/\b\d+\b/'],
            ['?', '?'],
            $rawSql
        );

        if (!isset($this->queryCounts[$signature])) {
            $this->queryCounts[$signature] = 0;
            $this->templatedQueries[$signature] = $rawSql;
        }

        $this->queryCounts[$signature]++;
    }

    /**
     * Returns computed repeating query templates exceeding loop thresholds
     */
    public function getRepeatingQueries(): array
    {
        $loops = [];
        foreach ($this->queryCounts as $signature => $count) {
            if ($count >= 5) {
                // Try resolving dynamic relationship mappings
                $remedy = $this->guessRelationshipRemedy($signature);
                $loops[] = [
                    'query' => $this->templatedQueries[$signature],
                    'count' => $count,
                    'remedy' => $remedy,
                ];
            }
        }
        return $loops;
    }

    /**
     * Guesses relationship maps to generate eager load remedies
     */
    protected function guessRelationshipRemedy(string $signature): string
    {
        // Match standard model lookup filters, e.g. "where `id` = ?" or "where `user_id` = ?"
        if (preg_match('/select \* from `([^`]+)` where/i', $signature, $matches)) {
            $table = $matches[1];
            $model = str_replace(' ', '', ucwords(str_replace('_', ' ', rtrim($table, 's'))));
            return "Eager load relationship (e.g. Model::with('" . rtrim($table, 's') . "')) in your controller.";
        }

        return "Eager load relationships to prevent redundant database fetches.";
    }
}
