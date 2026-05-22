<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Controller to handle execution of Artisan console actions and PHPUnit tests.
 */
final class ArtisanController
{
    /**
     * Run specific predefined artisan actions inside the application.
     *
     * @param string $command
     * @return JsonResponse
     */
    public function runArtisan(string $command): JsonResponse
    {
        try {
            if ($command === 'cache-clear') {
                Artisan::call('cache:clear');
                return response()->json([
                    'success' => true,
                    'message' => 'Cache cleared successfully!'
                ]);
            }

            if ($command === 'route-clear') {
                Artisan::call('route:clear');
                return response()->json([
                    'success' => true,
                    'message' => 'Route cache cleared successfully!'
                ]);
            }

            if ($command === 'debug-clean') {
                Artisan::call('agent:debug-clean');
                return response()->json([
                    'success' => true,
                    'message' => 'Agent request logs cleared successfully!'
                ]);
            }
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Command not found.'
        ], 404);
    }

    /**
     * Execute local PHPUnit verification tests and return command output.
     *
     * @return JsonResponse
     */
    public function runTests(): JsonResponse
    {
        try {
            $output = [];
            $resultCode = 0;
            exec('vendor/bin/phpunit tests/Feature/ 2>&1', $output, $resultCode);

            return response()->json([
                'success' => true,
                'exit_code' => $resultCode,
                'output' => implode("\n", $output)
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
