<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

/**
 * Service to analyze query lists for performance anomalies (N+1, duplicates, missing indexes).
 */
class QueryAnalyzer
{
    /**
     * Identifies repeating queries (N+1 query loops) based on signatures.
     *
     * @param array<int, array{sql: string}> $queries
     * @return array<int, array{query: string, count: int, remedy: string}>
     */
    public function getRepeatingQueries(array $queries): array
    {
        if (!config('agent-debugger.detect_n_plus_one', true)) {
            return [];
        }

        $queryCounts = [];
        $templatedQueries = [];

        foreach ($queries as $q) {
            $rawSql = $q['sql'];
            // Replace dynamic parameters with wildcards to create signature
            $signature = preg_replace(
                ['/\'[^\']+\'/', '/\b\d+\b/'],
                ['?', '?'],
                $rawSql
            );

            if ($signature === null) {
                continue;
            }

            if (!isset($queryCounts[$signature])) {
                $queryCounts[$signature] = 0;
                $templatedQueries[$signature] = $rawSql;
            }
            $queryCounts[$signature]++;
        }

        $loops = [];
        foreach ($queryCounts as $signature => $count) {
            if ($count >= 5) {
                $loops[] = [
                    'query' => $templatedQueries[$signature],
                    'count' => $count,
                    'remedy' => $this->guessRelationshipRemedy($signature),
                ];
            }
        }

        return $loops;
    }

    /**
     * Detect exact duplicate query executions.
     *
     * @param array<int, array{sql: string}> $queries
     * @return array<int, array{sql: string, count: int, remedy: string}>
     */
    public function getRedundantQueries(array $queries): array
    {
        $exactQueryCounts = [];
        foreach ($queries as $q) {
            $sql = $q['sql'];
            $exactQueryCounts[$sql] = ($exactQueryCounts[$sql] ?? 0) + 1;
        }

        $redundant = [];
        foreach ($exactQueryCounts as $sql => $count) {
            if ($count > 1) {
                $redundant[] = [
                    'sql' => $sql,
                    'count' => $count,
                    'remedy' => "Cache the query result using Cache::remember() or optimize your database query lifecycle."
                ];
            }
        }

        return $redundant;
    }

    /**
     * Suggest database indexes based on executed SQL queries.
     *
     * @param array<int, array{sql: string}> $queries
     * @return array<int, array{table: string, column: string, sql: string, recommendation: string}>
     */
    public function getIndexAdvice(array $queries): array
    {
        $exactQueryCounts = [];
        foreach ($queries as $q) {
            $sql = $q['sql'];
            $exactQueryCounts[$sql] = ($exactQueryCounts[$sql] ?? 0) + 1;
        }

        $advice = [];
        foreach (array_keys($exactQueryCounts) as $sql) {
            if (preg_match('/from `([^`]+)`/i', $sql, $tblMatches)) {
                $table = $tblMatches[1];
                if (preg_match('/where `([^`]+)`\s*(?:=|<|>|like)/i', $sql, $colMatches)) {
                    $column = $colMatches[1];
                    if ($column !== 'id' && !str_ends_with($column, '_id')) {
                        $advice[] = [
                            'table' => $table,
                            'column' => $column,
                            'sql' => $sql,
                            'recommendation' => "Add database index on `{$table}({$column})` using migrations to optimize lookup performance."
                        ];
                    }
                }
            }
        }

        return $advice;
    }

    /**
     * Guesses relationship maps to generate eager load remedies.
     */
    protected function guessRelationshipRemedy(string $signature): string
    {
        if (preg_match('/select \* from `([^`]+)` where/i', $signature, $matches)) {
            $table = $matches[1];
            return "Eager load relationship (e.g. Model::with('" . rtrim($table, 's') . "')) in your controller.";
        }
        return "Eager load relationships to prevent redundant database fetches.";
    }
}
