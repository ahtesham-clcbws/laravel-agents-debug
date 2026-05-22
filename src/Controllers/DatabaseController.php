<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Controller to handle database diagnostic profiling and sandbox querying.
 */
final class DatabaseController
{
    /**
     * Explain the SQL SELECT query using database server query plan details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function explain(Request $request): JsonResponse
    {
        try {
            $sql = $request->input('sql');
            if (empty($sql) || !is_string($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SQL query provided.'
                ], 400);
            }

            if (!preg_match('/^\s*select/i', $sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only SELECT queries can be explained.'
                ], 400);
            }

            $explain = DB::select("EXPLAIN " . $sql);
            return response()->json([
                'success' => true,
                'explain' => $explain
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute SELECT queries in the database playground sandbox.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function playground(Request $request): JsonResponse
    {
        try {
            $sql = $request->input('sql');
            if (empty($sql) || !is_string($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SQL query provided.'
                ], 400);
            }

            if (!preg_match('/^\s*select/i', $sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only SELECT queries are allowed in the SQL Playground.'
                ], 400);
            }

            $results = DB::select($sql);
            // Return up to first 50 results to prevent memory spikes
            return response()->json([
                'success' => true,
                'results' => array_slice($results, 0, 50)
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
