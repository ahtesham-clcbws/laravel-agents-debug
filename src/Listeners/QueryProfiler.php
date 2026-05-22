<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use LaravelAgentDebugger\DebugLoggerManager;

/**
 * Event listener to profile database queries and transactions during the request lifecycle.
 */
class QueryProfiler
{
    protected DebugLoggerManager $manager;

    /**
     * Create a new query profiler instance.
     *
     * @param DebugLoggerManager $manager
     */
    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Register DB operation event listeners.
     *
     * @return void
     */
    public function subscribe(): void
    {
        DB::listen(function (QueryExecuted $event): void {
            $this->logQuery($event);
        });

        $dispatcher = DB::connection()->getEventDispatcher();

        if ($dispatcher) {
            $dispatcher->listen(TransactionBeginning::class, function (): void {
                $this->logTransaction('DB::beginTransaction()');
            });

            $dispatcher->listen(TransactionCommitted::class, function (): void {
                $this->logTransaction('DB::commit()');
            });

            $dispatcher->listen(TransactionRolledBack::class, function (): void {
                $this->logTransaction('DB::rollBack()');
            });
        }
    }

    /**
     * Format and record SQL query execution details.
     *
     * @param QueryExecuted $event
     * @return void
     */
    protected function logQuery(QueryExecuted $event): void
    {
        $sql = $event->sql;
        $bindings = $event->bindings;
        $time = $event->time;

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

        $slowThreshold = (float)config('agent-debugger.slow_query_threshold', 10.0);
        $isSlow = $slowThreshold > 0 && $time >= $slowThreshold;

        $fileLine = null;
        if (config('agent-debugger.log_query_source', true)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            foreach ($trace as $step) {
                if (isset($step['file']) &&
                    !str_contains($step['file'], 'vendor/') &&
                    !str_contains($step['file'], 'laravel-agents-debug') &&
                    !str_contains($step['file'], 'Local_Debug_Activity')
                ) {
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
    }

    /**
     * Record database transaction action.
     *
     * @param string $type
     * @return void
     */
    protected function logTransaction(string $type): void
    {
        $fileLine = null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($trace as $step) {
            if (isset($step['file']) && !str_contains($step['file'], 'vendor/')) {
                $fileLine = basename($step['file']) . ' line ' . ($step['line'] ?? 0);
                break;
            }
        }

        $this->manager->addTransaction($type, 0.0, $fileLine);
    }
}
