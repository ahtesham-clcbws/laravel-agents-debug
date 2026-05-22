<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service to parse, redact, and store Inertia and Livewire page payloads.
 */
class PayloadProcessor
{
    /**
     * Create a new payload processor instance.
     */
    public function __construct(
        protected readonly LogCompiler $logCompiler
    ) {}

    /**
     * Extracts and dumps full untruncated Inertia props to separate JSON files.
     *
     * @param Request $request
     * @param Response $response
     * @return array{component: string, url: string, file_link: string}|null
     */
    public function processInertiaPayload(Request $request, Response $response): ?array
    {
        $inertiaData = null;

        if ($request->hasHeader('X-Inertia')) {
            if ($response instanceof \Symfony\Component\HttpFoundation\JsonResponse) {
                $data = json_decode((string)$response->getContent(), true);
                if (is_array($data) && isset($data['component']) && isset($data['props'])) {
                    $inertiaData = $data;
                }
            }
        } else {
            $html = $response->getContent();
            if (is_string($html) && preg_match('/data-page="([^"]+)"/', $html, $matches)) {
                $decodedJson = json_decode(html_entity_decode($matches[1]), true);
                if (is_array($decodedJson) && isset($decodedJson['component']) && isset($decodedJson['props'])) {
                    $inertiaData = $decodedJson;
                }
            }
        }

        if (!$inertiaData) {
            return null;
        }

        if (isset($inertiaData['props']) && is_array($inertiaData['props'])) {
            $inertiaData['props'] = $this->logCompiler->redactArray($inertiaData['props']);
        }

        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $inertiaDir = $logPath . '/agent-debugger/inertia';
        if (!is_dir($inertiaDir)) {
            mkdir($inertiaDir, 0755, true);
        }

        $requestId = uniqid();
        $filename = "inertia_req_{$requestId}.json";
        $filePath = $inertiaDir . '/' . $filename;
        
        file_put_contents($filePath, json_encode($inertiaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'component' => (string)$inertiaData['component'],
            'url' => (string)($inertiaData['url'] ?? $request->getRequestUri()),
            'file_link' => 'file://' . $filePath
        ];
    }

    /**
     * Extracts and dumps Livewire hydration payload properties to separate JSON files.
     *
     * @param Request $request
     * @param Response $response
     * @return array{component: string, file_link: string}|null
     */
    public function processLivewirePayload(Request $request, Response $response): ?array
    {
        if (!$request->hasHeader('X-Livewire') && !str_contains((string)$request->getPathInfo(), '/livewire')) {
            return null;
        }

        $components = $request->input('components') ?: [];
        if (empty($components)) {
            $serverMemo = $request->input('serverMemo') ?: [];
            if (empty($serverMemo) && !$request->input('fingerprint')) {
                return null;
            }
            $components = [$request->all()];
        }

        $redactedComponents = $this->logCompiler->redactArray($components);

        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $livewireDir = $logPath . '/agent-debugger/livewire';
        if (!is_dir($livewireDir)) {
            mkdir($livewireDir, 0755, true);
        }

        $requestId = uniqid();
        $filename = "livewire_req_{$requestId}.json";
        $filePath = $livewireDir . '/' . $filename;
        
        file_put_contents($filePath, json_encode($redactedComponents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $componentName = 'LivewireComponent';
        if (isset($redactedComponents[0]['fingerprint']['name'])) {
            $componentName = (string)$redactedComponents[0]['fingerprint']['name'];
        } elseif (isset($redactedComponents[0]['name'])) {
            $componentName = (string)$redactedComponents[0]['name'];
        }

        return [
            'component' => $componentName,
            'file_link' => 'file://' . $filePath
        ];
    }
}
