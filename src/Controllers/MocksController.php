<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Controller to manage outgoing Guzzle/HTTP Client mocks and interceptors configuration.
 */
final class MocksController
{
    /**
     * Get faked endpoints configuration list.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $mocksFile = storage_path('logs/agent-debugger/mocks.json');
        $mocks = file_exists($mocksFile)
            ? json_decode((string)file_get_contents($mocksFile), true)
            : [];

        return response()->json(is_array($mocks) ? $mocks : []);
    }

    /**
     * Store and persist mocks rules to the storage logs path.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function save(Request $request): JsonResponse
    {
        try {
            $mocksFile = storage_path('logs/agent-debugger/mocks.json');
            $mocksDir = dirname($mocksFile);

            if (!is_dir($mocksDir)) {
                mkdir($mocksDir, 0755, true);
            }

            $mocks = $request->input('mocks', []);
            file_put_contents($mocksFile, json_encode($mocks, JSON_PRETTY_PRINT));

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
