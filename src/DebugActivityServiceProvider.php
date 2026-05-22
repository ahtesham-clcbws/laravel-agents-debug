<?php

declare(strict_types=1);

namespace LaravelAgentDebugger;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use LaravelAgentDebugger\Commands\DebugOnCommand;
use LaravelAgentDebugger\Commands\DebugOffCommand;
use LaravelAgentDebugger\Commands\DebugStatusCommand;
use LaravelAgentDebugger\Commands\DebugCleanCommand;
use LaravelAgentDebugger\Commands\DebugTailCommand;
use LaravelAgentDebugger\Middleware\DebugActivityLogger;
use LaravelAgentDebugger\Middleware\ViewportBorderInjector;

class DebugActivityServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge package configuration defaults
        $this->mergeConfigFrom(
            __DIR__ . '/../config/agent-debugger.php',
            'agent-debugger'
        );

        // Register package state manager
        $this->app->singleton(DebugLoggerManager::class, function ($app) {
            return new DebugLoggerManager($app);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(Kernel $kernel): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/agent-debugger.php' => config_path('agent-debugger.php'),
            ], 'agent-debugger-config');

            // Register Artisan commands
            $this->commands([
                DebugOnCommand::class,
                DebugOffCommand::class,
                DebugStatusCommand::class,
                DebugCleanCommand::class,
                DebugTailCommand::class,
            ]);

            // Auto-clean logs on local serve startup
            $this->autoCleanLogsOnServe();
        }

        // If not enabled globally, stop here
        if (!config('agent-debugger.enabled', false)) {
            return;
        }

        // Register local dashboard routes
        $this->app['router']->get('_agent_debug/dashboard', function () {
            return response($this->getDashboardHtml(), 200, ['Content-Type' => 'text/html']);
        });

        $this->app['router']->get('_agent_debug/logs', function () {
            return response()->json($this->getParsedLogs());
        });

        // Programmatically register the Global HTTP Middleware
        $kernel->prependMiddleware(DebugActivityLogger::class);

        // Register visual frame injector if enabled
        if (config('agent-debugger.show_frontend_indicator', true)) {
            $kernel->appendMiddleware(ViewportBorderInjector::class);
        }
    }

    /**
     * Automatically purges logs if started via php artisan serve commands
     */
    protected function autoCleanLogsOnServe(): void
    {
        if (!config('agent-debugger.auto_clean_debug', true)) {
            return;
        }

        $args = $_SERVER['argv'] ?? [];
        
        // Match: artisan serve
        $isServe = false;
        foreach ($args as $arg) {
            if ($arg === 'serve') {
                $isServe = true;
                break;
            }
        }

        if ($isServe) {
            $logPath = config('agent-debugger.log_path', storage_path('logs'));
            $logStyle = config('agent-debugger.log_style', 'date-wise');

            if (!is_dir($logPath)) {
                return;
            }

            if ($logStyle === 'single') {
                $target = $logPath . '/agent_debug.log';
                if (file_exists($target)) {
                    file_put_contents($target, '');
                }
            } else {
                $files = glob($logPath . '/agent_debug-*.log');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                }
            }

            // Clean all Inertia payload logs on serve startup
            $inertiaFiles = glob($logPath . '/agent-debugger/inertia/*.json');
            if (is_array($inertiaFiles)) {
                foreach ($inertiaFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Parse localized debug logs into structured segments for the dashboard UI
     */
    protected function getParsedLogs(): array
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $logStyle = config('agent-debugger.log_style', 'date-wise');
        $fileName = $logStyle === 'single'
            ? 'agent_debug.log'
            : 'agent_debug-' . date('Y-m-d') . '.log';

        $filePath = $logPath . '/' . $fileName;

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        $blocks = explode(str_repeat('=', 80), $content);
        $parsed = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            // Extract front-matter details
            $timestamp = '';
            $method = 'GET';
            $url = '';
            $status = 200;
            $duration = 0.0;
            $memory = 0.0;
            $crashed = false;
            $queriesCount = 0;
            $errorsCount = 0;

            if (preg_match('/timestamp:\s*"(.*?)"/', $block, $m)) $timestamp = $m[1];
            if (preg_match('/method:\s*"(.*?)"/', $block, $m)) $method = $m[1];
            if (preg_match('/url:\s*"(.*?)"/', $block, $m)) $url = $m[1];
            if (preg_match('/status:\s*(\d+)/', $block, $m)) $status = (int)$m[1];
            if (preg_match('/execution_time_ms:\s*([\d\.]+)/', $block, $m)) $duration = (float)$m[1];
            if (preg_match('/memory_peak_mb:\s*([\d\.]+)/', $block, $m)) $memory = (float)$m[1];
            if (preg_match('/crashed:\s*(true|false)/', $block, $m)) $crashed = $m[1] === 'true';
            if (preg_match('/queries_count:\s*(\d+)/', $block, $m)) $queriesCount = (int)$m[1];
            if (preg_match('/errors_count:\s*(\d+)/', $block, $m)) $errorsCount = (int)$m[1];

            $inertiaData = null;
            if (preg_match('/Payload Link:\s*(file:\/\/.*?\.json)/', $block, $m)) {
                $realPath = str_replace('file://', '', $m[1]);
                if (file_exists($realPath)) {
                    $inertiaData = json_decode(file_get_contents($realPath), true);
                }
            }

            $parsed[] = [
                'timestamp' => $timestamp,
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'duration' => $duration,
                'memory' => $memory,
                'crashed' => $crashed,
                'queries_count' => $queriesCount,
                'errors_count' => $errorsCount,
                'inertia_data' => $inertiaData,
                'raw' => $block
            ];
        }

        return array_reverse($parsed);
    }

    /**
     * Renders premium visual single-page application dashboard layout
     */
    protected function getDashboardHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Agent-Debugger Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        pre, code { font-family: 'JetBrains Mono', monospace; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.6); }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.5); }
    </style>
</head>
<body class="h-full">
    <div id="app" class="h-full flex flex-col">
        <!-- Top Navbar -->
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-900/80 backdrop-blur-md z-10">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold bg-gradient-to-r from-red-500 via-orange-400 to-yellow-500 bg-clip-text text-transparent">laravel-agents-debug</span>
                <span class="px-2 py-0.5 text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700 rounded-full">v2.8.1</span>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Polling Control -->
                <button @click="togglePolling" :class="isPolling ? 'bg-red-500/20 text-red-400 border-red-500/40' : 'bg-slate-800 text-slate-300 border-slate-700'" class="flex items-center space-x-2 px-3 py-1.5 text-sm rounded-lg border transition-all duration-200">
                    <span :class="isPolling ? 'animate-pulse bg-red-400' : 'bg-slate-500'" class="h-2 w-2 rounded-full"></span>
                    <span>{{ isPolling ? 'Live Polling Active' : 'Polling Paused' }}</span>
                </button>
                
                <button @click="fetchLogs" class="bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 px-3 py-1.5 text-sm rounded-lg transition-all duration-150">
                    Refresh
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Sidebar: Log Request List -->
            <aside class="w-1/3 border-r border-slate-800 bg-slate-950 flex flex-col">
                <div class="p-4 border-b border-slate-900 flex space-x-2">
                    <input type="text" v-model="search" placeholder="Filter by URL, Status, Method..." class="w-full bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-red-500 transition-colors">
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-slate-900">
                    <div v-for="(log, idx) in filteredLogs" :key="idx" @click="selectLog(log)" :class="selectedLog === log ? 'bg-slate-900/60 border-l-4 border-red-500' : 'hover:bg-slate-900/30 border-l-4 border-transparent'" class="p-4 cursor-pointer transition-all duration-150">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center space-x-2">
                                <span :class="getMethodClass(log.method)" class="px-1.5 py-0.5 text-[10px] font-bold rounded">{{ log.method }}</span>
                                <span :class="getStatusClass(log.status)" class="px-1.5 py-0.5 text-[10px] font-bold rounded">{{ log.status }}</span>
                            </div>
                            <span class="text-xs text-slate-500">{{ formatTime(log.timestamp) }}</span>
                        </div>
                        <div class="text-sm font-medium text-slate-300 truncate font-mono">{{ log.url }}</div>
                        
                        <div class="flex items-center space-x-3 mt-2 text-xs text-slate-500">
                            <span>⏱️ {{ log.duration }}ms</span>
                            <span>🗄️ {{ log.queries_count }} queries</span>
                            <span v-if="log.errors_count > 0" class="text-red-400">🔥 {{ log.errors_count }} error(s)</span>
                        </div>
                    </div>
                    
                    <div v-if="filteredLogs.length === 0" class="p-8 text-center text-slate-600">
                        No debug request logs found.
                    </div>
                </div>
            </aside>

            <!-- Detail Pane -->
            <main class="flex-1 bg-slate-950 flex flex-col overflow-hidden">
                <div v-if="selectedLog" class="flex-1 flex flex-col overflow-hidden">
                    <!-- Detail Header Summary -->
                    <div class="p-6 border-b border-slate-800 bg-slate-900/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center space-x-3 mb-1">
                                <span :class="getMethodClass(selectedLog.method)" class="px-2 py-0.5 text-xs font-bold rounded">{{ selectedLog.method }}</span>
                                <span :class="getStatusClass(selectedLog.status)" class="px-2 py-0.5 text-xs font-bold rounded">{{ selectedLog.status }}</span>
                                <h2 class="text-lg font-bold font-mono text-slate-100">{{ selectedLog.url }}</h2>
                            </div>
                            <p class="text-sm text-slate-400 font-mono">{{ selectedLog.timestamp }}</p>
                        </div>
                        
                        <div class="flex space-x-4">
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-[80px]">
                                <div class="text-xs text-slate-500">Duration</div>
                                <div class="text-lg font-semibold text-orange-400">{{ selectedLog.duration }}<span class="text-xs">ms</span></div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-[80px]">
                                <div class="text-xs text-slate-500">Memory</div>
                                <div class="text-lg font-semibold text-yellow-400">{{ selectedLog.memory }}<span class="text-xs">MB</span></div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-[80px]">
                                <div class="text-xs text-slate-500">Queries</div>
                                <div class="text-lg font-semibold text-green-400">{{ selectedLog.queries_count }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Switcher (Inertia only) -->
                    <div v-if="selectedLog.inertia_data" class="px-6 py-2 bg-slate-900/40 border-b border-slate-800 flex space-x-4">
                        <button @click="activeTab = 'log'" :class="activeTab === 'log' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150">📜 Execution Log</button>
                        <button @click="activeTab = 'inertia'" :class="activeTab === 'inertia' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150">📦 Inertia Properties</button>
                    </div>

                    <!-- Raw Formatted Output Block -->
                    <div v-if="activeTab === 'log'" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <pre class="text-sm text-slate-300 leading-relaxed font-mono whitespace-pre-wrap selection:bg-red-500/30 selection:text-white" v-html="highlightedContent"></pre>
                    </div>

                    <!-- Inertia Props Tab -->
                    <div v-if="activeTab === 'inertia' && selectedLog.inertia_data" class="flex-1 p-6 overflow-auto bg-slate-950/40 font-mono text-sm">
                        <div class="mb-4">
                            <span class="text-slate-500">Component:</span> <span class="text-white font-bold">{{ selectedLog.inertia_data.component }}</span>
                        </div>
                        <div class="mb-4">
                            <span class="text-slate-500">URL:</span> <span class="text-cyan-400 font-bold">{{ selectedLog.inertia_data.url }}</span>
                        </div>
                        <div class="border border-slate-800 rounded-lg bg-slate-900/60 p-4">
                            <div class="text-slate-400 font-bold mb-2">View Props Payload:</div>
                            <pre class="text-green-400 font-mono whitespace-pre-wrap select-all">{{ JSON.stringify(selectedLog.inertia_data.props, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
                
                <div v-else class="flex-1 flex flex-col items-center justify-center text-slate-500">
                    <span class="text-4xl mb-2">🔍</span>
                    <span>Select a request log from the sidebar to inspect complete backend execution states.</span>
                </div>
            </main>
        </div>
    </div>

    <script>
        const { createApp, ref, computed, onMounted, onUnmounted } = Vue;

        createApp({
            setup() {
                const logs = ref([]);
                const selectedLog = ref(null);
                const search = ref('');
                const isPolling = ref(true);
                let pollInterval = null;

                const activeTab = ref('log');

                const fetchLogs = async () => {
                    try {
                        const res = await fetch('/_agent_debug/logs');
                        const data = await res.json();
                        logs.value = data;
                        if (data.length > 0 && !selectedLog.value) {
                            selectedLog.value = data[0];
                        }
                    } catch (e) {
                        console.error("Could not fetch logs", e);
                    }
                };

                const selectLog = (log) => {
                    selectedLog.value = log;
                    activeTab.value = 'log';
                };

                const togglePolling = () => {
                    isPolling.value = !isPolling.value;
                    if (isPolling.value) {
                        startPolling();
                    } else {
                        stopPolling();
                    }
                };

                const startPolling = () => {
                    pollInterval = setInterval(fetchLogs, 2000);
                };

                const stopPolling = () => {
                    if (pollInterval) {
                        clearInterval(pollInterval);
                    }
                };

                onMounted(() => {
                    fetchLogs();
                    startPolling();
                });

                onUnmounted(() => {
                    stopPolling();
                });

                const filteredLogs = computed(() => {
                    if (!search.value) return logs.value;
                    const query = search.value.toLowerCase();
                    return logs.value.filter(log => 
                        log.url.toLowerCase().includes(query) || 
                        log.method.toLowerCase().includes(query) || 
                        log.status.toString().includes(query)
                    );
                });

                const highlightedContent = computed(() => {
                    if (!selectedLog.value) return '';
                    let text = selectedLog.value.raw;
                    
                    // Safely html escape
                    text = text
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;");

                    // Highlight warnings and loops
                    text = text.replace(/(⚠️ WARNING:.*)/g, '<span class="text-yellow-400 font-bold bg-yellow-950/40 px-1 py-0.5 rounded border border-yellow-800/40">$1</span>');
                    text = text.replace(/(👉 Solution:.*)/g, '<span class="text-green-400 font-bold bg-green-950/40 px-1 py-0.5 rounded">$1</span>');

                    // Highlight query methods
                    text = text.replace(/(select \* from|insert into|update|delete from)/gi, '<span class="text-green-400 font-semibold">$1</span>');

                    // Highlight transaction commits and rollbacks
                    text = text.replace(/(DB::beginTransaction\(\))/g, '<span class="text-cyan-400 font-bold">$1</span>');
                    text = text.replace(/(DB::commit\(\))/g, '<span class="text-emerald-400 font-bold">$1</span>');
                    text = text.replace(/(DB::rollBack\(\))/g, '<span class="text-rose-400 font-bold">$1</span>');

                    // Highlight execution exception stack logs
                    text = text.replace(/(Class:.*)/g, '<span class="text-rose-400 font-semibold">$1</span>');
                    text = text.replace(/(Message:.*)/g, '<span class="text-rose-300 font-semibold">$1</span>');
                    text = text.replace(/(File:.*)/g, '<span class="text-slate-400 font-mono">$1</span>');
                    text = text.replace(/(Line:.*)/g, '<span class="text-yellow-400 font-mono">$1</span>');

                    return text;
                });

                const getMethodClass = (method) => {
                    switch (method) {
                        case 'GET': return 'bg-cyan-900/60 text-cyan-400 border border-cyan-800/40';
                        case 'POST': return 'bg-emerald-900/60 text-emerald-400 border border-emerald-800/40';
                        case 'PUT': case 'PATCH': return 'bg-amber-900/60 text-amber-400 border border-amber-800/40';
                        case 'DELETE': return 'bg-rose-900/60 text-rose-400 border border-rose-800/40';
                        default: return 'bg-slate-900/60 text-slate-400 border border-slate-800/40';
                    }
                };

                const getStatusClass = (status) => {
                    if (status >= 500) return 'bg-rose-900/60 text-rose-400 border border-rose-800/40';
                    if (status >= 400) return 'bg-orange-900/60 text-orange-400 border border-orange-800/40';
                    if (status >= 300) return 'bg-yellow-900/60 text-yellow-400 border border-yellow-800/40';
                    return 'bg-emerald-900/60 text-emerald-400 border border-emerald-800/40';
                };

                const formatTime = (ts) => {
                    if (!ts) return '';
                    const parts = ts.split(' ');
                    return parts.length > 1 ? parts[1] : ts;
                };

                return {
                    activeTab,
                    logs,
                    selectedLog,
                    search,
                    isPolling,
                    filteredLogs,
                    highlightedContent,
                    fetchLogs,
                    selectLog,
                    togglePolling,
                    getMethodClass,
                    getStatusClass,
                    formatTime
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
HTML;
    }
}
