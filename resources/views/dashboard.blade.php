<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Agent-Debugger Dashboard</title>
    <script src="/_agent_debug/assets/tailwind.js"></script>
    <script src="/_agent_debug/assets/vue.js"></script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.6); }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.5); }
    </style>
</head>
<body class="h-full">
@verbatim
    <div id="app" class="h-full flex flex-col">
        <!-- Top Navbar -->
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-900/80 backdrop-blur-md z-10">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold bg-linear-to-r from-red-500 via-orange-400 to-yellow-500 bg-clip-text text-transparent">laravel-agents-debug</span>
                <span class="px-2 py-0.5 text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700 rounded-full">v3.1.1</span>
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
                    <input type="text" v-model="search" placeholder="Filter by URL, Status, Method..." class="flex-1 bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-red-500 transition-colors">
                    <input type="text" v-model="tagFilter" placeholder="Tag..." class="w-1/4 bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-red-500 transition-colors" title="Filter by Category Tag (?_debug_tag=x)">
                </div>

                <!-- Interactive Artisan Quick-Console -->
                <div class="px-4 py-2 border-b border-slate-900 bg-slate-900/40 flex items-center justify-between text-xs">
                    <span class="font-bold uppercase tracking-wider text-slate-400">⚡ Console Actions</span>
                    <div class="flex space-x-2">
                        <button @click="runConsole('cache-clear')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 font-semibold rounded text-slate-300 transition" title="Clear Application Cache">🧹 Cache</button>
                        <button @click="runConsole('route-clear')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 font-semibold rounded text-slate-300 transition" title="Clear Route Cache">🔀 Route</button>
                        <button @click="runConsole('debug-clean')" class="px-2 py-1 bg-rose-955/60 hover:bg-rose-900 border border-rose-800 font-semibold rounded text-rose-300 transition" title="Clear Request Logs">🚿 Clean</button>
                    </div>
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
                                <span v-if="selectedLog.debug_tag && selectedLog.debug_tag !== 'default'" class="px-2 py-0.5 text-xs font-bold bg-teal-900 border border-teal-700 text-teal-300 rounded">🏷️ {{ selectedLog.debug_tag }}</span>
                                <span v-if="selectedLog.git_branch && selectedLog.git_branch !== 'N/A'" class="px-2 py-0.5 text-xs font-bold bg-purple-900/60 border border-purple-700 text-purple-300 rounded flex items-center">
                                    <span>🚀 git:{{ selectedLog.git_branch }}</span>
                                    <span v-if="selectedLog.git_changed_files > 0" class="ml-1 px-1 bg-red-600 text-white rounded text-[10px] font-bold">{{ selectedLog.git_changed_files }}*</span>
                                </span>
                                <h2 class="text-lg font-bold font-mono text-slate-100">{{ selectedLog.url }}</h2>
                            </div>
                            <p class="text-sm text-slate-400 font-mono">{{ selectedLog.timestamp }}</p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <button @click="copyAsCurl(selectedLog)" class="px-3 py-2 bg-slate-900/80 hover:bg-slate-800 border border-slate-800 rounded-lg text-xs font-semibold text-slate-300 flex items-center space-x-1.5 transition" title="Copy request as curl command">
                                📋 cURL
                            </button>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-20">
                                <div class="text-xs text-slate-500">Duration</div>
                                <div class="text-lg font-semibold text-orange-400">{{ selectedLog.duration }}<span class="text-xs">ms</span></div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-20">
                                <div class="text-xs text-slate-500">Memory</div>
                                <div class="text-lg font-semibold text-yellow-400">{{ selectedLog.memory }}<span class="text-xs">MB</span></div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 text-center min-w-20">
                                <div class="text-xs text-slate-500">Queries</div>
                                <div class="text-lg font-semibold text-green-400">{{ selectedLog.queries_count }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Outgoing Latency Radar (Visual Performance Breakdown) -->
                    <div class="mx-6 mt-4 bg-slate-900/40 border border-slate-800 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center space-x-1.5">
                                <span>⚡ Outgoing Latency Radar</span>
                            </h3>
                            <span class="text-[10px] text-slate-400 font-mono">{{ selectedLog.duration }}ms Total</span>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div>
                                <div class="flex justify-between text-[11px] mb-1 font-mono">
                                    <span class="text-slate-400">Database Queries ({{ selectedLog.queries_count }} queries)</span>
                                    <span class="text-green-400 font-bold">{{ getDbTotalTime(selectedLog) }}ms ({{ getPercent(getDbTotalTime(selectedLog), selectedLog.duration) }}%)</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-green-500 h-full" :style="{ width: getPercent(getDbTotalTime(selectedLog), selectedLog.duration) + '%' }"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[11px] mb-1 font-mono">
                                    <span class="text-slate-400">Application Boot & Routing overhead</span>
                                    <span class="text-cyan-400 font-bold">{{ getAppOverhead(selectedLog) }}ms ({{ getPercent(getAppOverhead(selectedLog), selectedLog.duration) }}%)</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-cyan-500 h-full" :style="{ width: getPercent(getAppOverhead(selectedLog), selectedLog.duration) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Shield Warnings Alert Banner -->
                    <div v-if="selectedLog.env_warnings && selectedLog.env_warnings.length > 0" class="mx-6 mt-4 p-4 bg-rose-955/40 border border-rose-800/80 rounded-lg flex items-start space-x-3 text-rose-300">
                        <span class="text-xl">🚨</span>
                        <div>
                            <div class="font-bold text-rose-200">Dev Services Offline (Configuration Shield Alert)</div>
                            <div class="text-xs mt-1 space-y-1">
                                <div v-for="(warning, wIdx) in selectedLog.env_warnings" :key="wIdx">
                                    • <span class="font-semibold text-rose-100">[{{ warning.service }}]</span> {{ warning.message }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Database Index Advice Banner -->
                    <div v-if="selectedLog.index_advice && selectedLog.index_advice.length > 0" class="mx-6 mt-4 p-4 bg-emerald-950/40 border border-emerald-800/80 rounded-lg flex items-start space-x-3 text-emerald-300">
                        <span class="text-xl">💡</span>
                        <div>
                            <div class="font-bold text-emerald-200">Database Index Optimization Recommendations</div>
                            <div class="text-xs mt-1 space-y-2">
                                <div v-for="(adv, aIdx) in selectedLog.index_advice" :key="aIdx">
                                    • Index recommendation on table <span class="font-bold text-emerald-100">`{{ adv.table }}`</span> for column <span class="font-bold text-emerald-100">`{{ adv.column }}`</span>:
                                    <div class="text-[10px] text-slate-400 mt-0.5">👉 {{ adv.recommendation }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CSRF Token Validation Debugger Alert Banner -->
                    <div v-if="selectedLog.csrf_checked && !selectedLog.csrf_passed" class="mx-6 mt-4 p-4 bg-amber-950/40 border border-amber-800/80 rounded-lg flex items-start space-x-3 text-amber-300">
                        <span class="text-xl">🍪</span>
                        <div>
                            <div class="font-bold text-amber-200">CSRF Token Mismatch Alert (Potential 419 Page Expired)</div>
                            <div class="text-xs mt-1 text-amber-400">
                                {{ selectedLog.csrf_reason }} Ensure that your forms include a `@csrf` token directive or that your AJAX headers contain a valid `X-CSRF-TOKEN` or decrypted `X-XSRF-TOKEN` credential.
                            </div>
                        </div>
                    </div>

                    <!-- .env vs .env.example Audit Warning Banner -->
                    <div v-if="selectedLog.env_drifts && selectedLog.env_drifts.length > 0" class="mx-6 mt-4 p-4 bg-cyan-950/40 border border-cyan-800/80 rounded-lg flex items-start space-x-3 text-cyan-300">
                        <span class="text-xl">⚖️</span>
                        <div>
                            <div class="font-bold text-cyan-200">.env / .env.example Configuration Divergence</div>
                            <div class="text-xs mt-1 space-y-1">
                                <div v-for="(drift, dIdx) in selectedLog.env_drifts" :key="dIdx">
                                    • <span class="font-semibold text-cyan-100">[{{ drift.status }}]</span> {{ drift.message }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Duplicate / Redundant queries banner -->
                    <div v-if="selectedLog.redundant_queries && selectedLog.redundant_queries.length > 0" class="mx-6 mt-4 p-4 bg-yellow-955/40 border border-yellow-800/80 rounded-lg flex items-start space-x-3 text-yellow-300">
                        <span class="text-xl">👥</span>
                        <div>
                            <div class="font-bold text-yellow-200">Duplicate / Redundant Database Queries Detected</div>
                            <div class="text-xs mt-1 space-y-2">
                                <div v-for="(red, rIdx) in selectedLog.redundant_queries" :key="rIdx">
                                    • Exact query executed <span class="font-bold text-yellow-100">{{ red.count }} times</span>:
                                    <div class="font-mono bg-slate-950/60 p-2 rounded text-slate-300 mt-1 select-all break-all">{{ red.sql }}</div>
                                    <div class="text-[10px] text-slate-400 mt-1">👉 {{ red.remedy }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Composer dependency advisory banner -->
                    <div v-if="selectedLog.composer_vulnerabilities && selectedLog.composer_vulnerabilities.length > 0" class="mx-6 mt-4 p-4 bg-rose-955/40 border border-rose-800/80 rounded-lg flex items-start space-x-3 text-rose-300">
                        <span class="text-xl">🩹</span>
                        <div>
                            <div class="font-bold text-rose-200">Security Packages Vulnerabilities Detected (Composer Audit)</div>
                            <div class="text-xs mt-1 space-y-2">
                                <div v-for="(vuln, vIdx) in selectedLog.composer_vulnerabilities" :key="vIdx">
                                    • <span class="font-semibold text-rose-100">{{ vuln.package }} ({{ vuln.installed }})</span>: {{ vuln.title }} - <span class="font-mono text-rose-400 font-semibold">{{ vuln.cve }}</span>
                                    <div class="text-[10px] text-slate-400 mt-0.5">👉 {{ vuln.recommendation }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Switcher -->
                    <div class="px-6 py-2 bg-slate-900/40 border-b border-slate-800 flex space-x-4 overflow-x-auto">
                        <button @click="activeTab = 'log'" :class="activeTab === 'log' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">📜 Execution Log</button>
                        <button v-if="selectedLog.queries_list && selectedLog.queries_list.length > 0" @click="activeTab = 'database'" :class="activeTab === 'database' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🗃️ Database Queries ({{ selectedLog.queries_count }})</button>
                        <button @click="activeTab = 'timeline'" :class="activeTab === 'timeline' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">⏱️ Timeline Waterfall</button>
                        <button v-if="(selectedLog.events && selectedLog.events.length > 0) || (selectedLog.jobs && selectedLog.jobs.length > 0)" @click="activeTab = 'events'" :class="activeTab === 'events' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">📢 Events & Jobs ({{ (selectedLog.events ? selectedLog.events.length : 0) + (selectedLog.jobs ? selectedLog.jobs.length : 0) }})</button>
                        <button v-if="selectedLog.views && selectedLog.views.length > 0" @click="activeTab = 'views'" :class="activeTab === 'views' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🗺️ Blade Views ({{ selectedLog.views.length }})</button>
                        <button v-if="selectedLog.inertia_data" @click="activeTab = 'inertia'" :class="activeTab === 'inertia' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">📦 Inertia Properties</button>
                        <button v-if="selectedLog.livewire_data" @click="activeTab = 'livewire'" :class="activeTab === 'livewire' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🔌 Livewire Properties</button>
                        <button v-if="selectedLog.cache_actions && selectedLog.cache_actions.length > 0" @click="activeTab = 'cache'" :class="activeTab === 'cache' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🗂️ Cache Monitor ({{ selectedLog.cache_actions_count }})</button>
                        <button v-if="selectedLog.eloquent_events && selectedLog.eloquent_events.length > 0" @click="activeTab = 'eloquent'" :class="activeTab === 'eloquent' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🔄 Eloquent Events ({{ selectedLog.eloquent_events_count }})</button>
                        <button v-if="selectedLog.emails && selectedLog.emails.length > 0" @click="activeTab = 'mail'" :class="activeTab === 'mail' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">📧 Outgoing Mail ({{ selectedLog.emails.length }})</button>
                        <button @click="activeTab = 'memory'" :class="activeTab === 'memory' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">📊 Memory Allocations</button>
                        <button @click="activeTab = 'mocks'" :class="activeTab === 'mocks' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🎭 Outgoing API Mocks</button>
                        <button @click="activeTab = 'tests'" :class="activeTab === 'tests' ? 'border-red-500 text-red-400 font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'" class="py-2 px-1 border-b-2 text-sm transition-all duration-150 whitespace-nowrap">🧪 PHPUnit Runner</button>
                    </div>

                    <!-- Raw Formatted Output Block -->
                    <div v-if="activeTab === 'log'" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <!-- Request Localization Info Widget -->
                        <div v-if="selectedLog.localization" class="mb-6 p-4 bg-slate-900/40 border border-slate-800 rounded-lg grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                            <div>
                                <div class="text-slate-500 font-semibold">🌐 Primary Language</div>
                                <div class="text-slate-300 font-mono mt-0.5">{{ selectedLog.localization.primary_locale }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500 font-semibold">🕒 App Timezone</div>
                                <div class="text-slate-300 font-mono mt-0.5">{{ selectedLog.localization.timezone }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500 font-semibold">💻 User IP</div>
                                <div class="text-slate-300 font-mono mt-0.5">{{ selectedLog.localization.ip_address }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500 font-semibold">📝 Preferred Headers</div>
                                <div class="text-slate-300 font-mono mt-0.5 truncate" :title="selectedLog.localization.accept_language">{{ selectedLog.localization.accept_language }}</div>
                            </div>
                        </div>
                        <pre class="text-sm text-slate-300 leading-relaxed font-mono whitespace-pre-wrap selection:bg-red-500/30 selection:text-white" v-html="highlightedContent"></pre>
                    </div>

                    <!-- Timeline Waterfall Tab -->
                    <div v-if="activeTab === 'timeline'" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                            <span>⏱️ Interactive DevTools-Style Timeline Waterfall</span>
                        </h3>
                        
                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 p-4 mb-6">
                            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 text-xs">
                                <div class="text-slate-400 font-bold">Execution Metric Breakdown</div>
                                <div class="text-slate-500 font-mono">Total timing: <span class="text-orange-400 font-bold font-mono">{{ selectedLog.duration }}ms</span></div>
                            </div>
                            
                            <div class="space-y-4">
                                <div v-for="(item, idx) in timelineWaterfall" :key="idx" class="grid grid-cols-1 md:grid-cols-4 gap-2 items-center text-xs">
                                    <div class="font-mono text-slate-400 truncate md:col-span-1" :title="item.name">
                                        <span :class="item.textClass" class="font-bold mr-1.5">•</span>
                                        {{ item.name }}
                                    </div>
                                    <div class="font-mono text-[10px] text-slate-500 md:col-span-1 md:text-right">
                                        {{ item.duration }}ms ({{ item.startPct }}% start offset)
                                    </div>
                                    <div class="md:col-span-2 bg-slate-900 h-4 rounded overflow-hidden relative border border-slate-800/40">
                                        <div :class="item.color" class="h-full rounded transition-all duration-300 absolute" :style="{ left: item.startPct + '%', width: item.durPct + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Side-Effect Events & Jobs Tab -->
                    <div v-if="activeTab === 'events'" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <div v-if="selectedLog.events && selectedLog.events.length > 0" class="mb-8">
                            <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                                <span>📢 Dispatched Application Events</span>
                            </h3>
                            <div class="space-y-4">
                                <div v-for="(e, eIdx) in selectedLog.events" :key="eIdx" class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 p-4">
                                    <div class="font-mono text-sm font-bold text-cyan-400 mb-2 flex items-center justify-between">
                                        <span>• {{ e.name }}</span>
                                        <span class="text-[10px] uppercase font-bold text-slate-500 bg-slate-900 border border-slate-800 px-2 py-0.5 rounded">Event</span>
                                    </div>
                                    <div v-if="e.payload && Object.keys(e.payload).length > 0" class="border border-slate-900 rounded bg-slate-950/60 p-3 mt-2">
                                        <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Serialized Event Payload:</div>
                                        <pre class="text-xs font-mono text-slate-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ JSON.stringify(e.payload, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedLog.jobs && selectedLog.jobs.length > 0">
                            <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                                <span>🎚️ Dispatched Background Queue Jobs</span>
                            </h3>
                            <div class="space-y-4">
                                <div v-for="(j, jIdx) in selectedLog.jobs" :key="jIdx" class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 p-4">
                                    <div class="font-mono text-sm font-bold text-purple-400 mb-2 flex items-center justify-between">
                                        <span>• {{ j.name }}</span>
                                        <span class="text-[10px] uppercase font-bold text-purple-400 bg-purple-955/40 border border-purple-800/40 px-2 py-0.5 rounded">Queue Job</span>
                                    </div>
                                    <div class="text-xs text-slate-400 mb-2">
                                        <span class="text-slate-500 font-semibold">Target Queue Channel:</span> <span class="font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-slate-300">{{ j.queue }}</span>
                                    </div>
                                    <div v-if="j.payload && Object.keys(j.payload).length > 0" class="border border-slate-900 rounded bg-slate-955/60 p-3 mt-2">
                                        <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Serialized Payload Fields:</div>
                                        <pre class="text-xs font-mono text-slate-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ JSON.stringify(j.payload, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Blade Views Composition Tree Tab -->
                    <div v-if="activeTab === 'views' && selectedLog.views && selectedLog.views.length > 0" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                            <span>🗺️ Interactive Blade Template Composition Tree</span>
                        </h3>
                        
                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 p-6 mb-6">
                            <div class="text-xs text-slate-400 mb-4 font-mono">
                                Nesting Render Sequence Breakdown (Nested child views compile first up to Layouts)
                            </div>
                            
                            <div class="flex flex-col space-y-4">
                                <div v-for="(v, vIdx) in selectedLog.views" :key="vIdx" class="flex items-center space-x-4">
                                    <div :class="v.startsWith('layouts.') || v.includes('layout') ? 'bg-purple-955/60 border-purple-800 text-purple-300' : (v.startsWith('components.') ? 'bg-cyan-955/60 border-cyan-800 text-cyan-300' : 'bg-slate-905 border-slate-800 text-slate-300')" class="border px-4 py-3 rounded-lg flex items-center justify-between font-mono text-sm shadow-md min-w-[280px]">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs">{{ v.startsWith('layouts.') || v.includes('layout') ? '🏛️' : (v.startsWith('components.') ? '🧩' : '📄') }}</span>
                                            <span class="font-bold">{{ v }}</span>
                                        </div>
                                        <span class="text-[10px] opacity-60 ml-3">#{{ vIdx + 1 }}</span>
                                    </div>
                                    
                                    <div v-if="vIdx < selectedLog.views.length - 1" class="text-slate-600 font-bold text-xl select-none">
                                        👉
                                    </div>
                                </div>
                            </div>
                        </div>
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

                    <!-- Livewire Properties Tab -->
                    <div v-if="activeTab === 'livewire' && selectedLog.livewire_data" class="flex-1 p-6 overflow-auto bg-slate-950/40 font-mono text-sm">
                        <div class="mb-4">
                            <span class="text-slate-500">Livewire Component:</span>
                            <span class="ml-2 font-bold text-red-400">{{ selectedLog.livewire_data.component }}</span>
                        </div>
                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950">
                            <div class="bg-slate-900/60 px-4 py-2 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                                <span>Hydrated Component State Parameters</span>
                                <button @click="copyToClipboard(JSON.stringify(selectedLog.livewire_data.payload, null, 2))" class="text-slate-400 hover:text-slate-200">Copy Payload</button>
                            </div>
                            <pre class="p-4 text-xs text-slate-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ JSON.stringify(selectedLog.livewire_data.payload, null, 2) }}</pre>
                        </div>
                    </div>

                    <!-- Cache Tab -->
                    <div v-if="activeTab === 'cache' && selectedLog.cache_actions && selectedLog.cache_actions.length > 0" class="flex-1 p-6 overflow-auto bg-slate-950/40 font-mono text-sm">
                        <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                            <span>🗂️ Cache Hit/Miss & Storage Latency Monitor</span>
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-slate-950/40 border border-slate-800/80 rounded-lg p-4">
                                <div class="text-xs text-slate-500">Hits</div>
                                <div class="text-2xl font-bold text-emerald-400">{{ selectedLog.cache_actions.filter(a => a.type === 'HIT').length }}</div>
                            </div>
                            <div class="bg-slate-955/40 border border-slate-800/80 rounded-lg p-4">
                                <div class="text-xs text-slate-500">Misses</div>
                                <div class="text-2xl font-bold text-rose-400">{{ selectedLog.cache_actions.filter(a => a.type === 'MISS').length }}</div>
                            </div>
                            <div class="bg-slate-950/40 border border-slate-800/80 rounded-lg p-4">
                                <div class="text-xs text-slate-500">Writes & Deletes</div>
                                <div class="text-2xl font-bold text-cyan-400">{{ selectedLog.cache_actions.filter(a => ['WRITE', 'FORGET'].includes(a.type)).length }}</div>
                            </div>
                        </div>

                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-900/20">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-slate-900/60 text-slate-400 uppercase font-bold border-b border-slate-800">
                                    <tr>
                                        <th class="px-4 py-3">Operation</th>
                                        <th class="px-4 py-3">Cache Key</th>
                                        <th class="px-4 py-3 text-right">Value Size</th>
                                        <th class="px-4 py-3 text-right">TTL</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <tr v-for="(action, index) in selectedLog.cache_actions" :key="index" class="hover:bg-slate-900/20 transition-colors">
                                        <td class="px-4 py-3">
                                            <span :class="{
                                                'bg-emerald-950/60 text-emerald-400 border-emerald-800/40': action.type === 'HIT',
                                                'bg-rose-955/60 text-rose-400 border-rose-800/40': action.type === 'MISS',
                                                'bg-cyan-955/60 text-cyan-400 border-cyan-800/40': action.type === 'WRITE',
                                                'bg-amber-955/60 text-amber-400 border-amber-800/40': action.type === 'FORGET'
                                            }" class="px-2 py-0.5 rounded text-[10px] font-bold border">{{ action.type }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-200 break-all select-all font-bold">{{ action.key }}</td>
                                        <td class="px-4 py-3 text-right text-slate-400">{{ action.size ? action.size + ' B' : 'N/A' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-400">{{ action.ttl ? action.ttl + 's' : 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Eloquent Lifecycle Tab -->
                    <div v-if="activeTab === 'eloquent' && selectedLog.eloquent_events && selectedLog.eloquent_events.length > 0" class="flex-1 p-6 overflow-auto bg-slate-950/40 font-mono text-sm">
                        <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                            <span>🔄 Eloquent Model Lifecycle & Observer Event Tracker</span>
                        </h3>

                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-900/20">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-slate-900/60 text-slate-400 uppercase font-bold border-b border-slate-800">
                                    <tr>
                                        <th class="px-4 py-3">Lifecycle Event</th>
                                        <th class="px-4 py-3">Eloquent Model</th>
                                        <th class="px-4 py-3 text-right">Primary Key (ID)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <tr v-for="(ee, index) in selectedLog.eloquent_events" :key="index" class="hover:bg-slate-900/20 transition-colors">
                                        <td class="px-4 py-3">
                                            <span :class="{
                                                'bg-emerald-950/60 text-emerald-400 border-emerald-800/40': ['creating', 'created', 'saving', 'saved'].includes(ee.event),
                                                'bg-cyan-955/60 text-cyan-400 border-cyan-800/40': ['updating', 'updated'].includes(ee.event),
                                                'bg-rose-955/60 text-rose-400 border-rose-800/40': ['deleting', 'deleted'].includes(ee.event)
                                            }" class="px-2 py-0.5 rounded text-[10px] font-bold border">{{ ee.event.toUpperCase() }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-200 break-all select-all font-bold">{{ ee.model }}</td>
                                        <td class="px-4 py-3 text-right text-slate-400 font-semibold">{{ ee.id || 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Database Queries & Playground Tab -->
                    <div v-if="activeTab === 'database' && selectedLog.queries_list && selectedLog.queries_list.length > 0" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <!-- Queries List -->
                        <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center">
                            <span>🗃️ Database Queries Profiler</span>
                        </h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="(q, qIdx) in selectedLog.queries_list" :key="qIdx" class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <span :class="q.is_slow ? 'bg-rose-955 text-rose-400 border-rose-800/60' : 'bg-green-950 text-green-400 border-green-800/60'" class="px-2 py-0.5 rounded text-xs font-bold border">
                                            {{ q.time }}ms
                                        </span>
                                        <span v-if="q.is_slow" class="text-xs text-rose-400 font-bold">⚠️ Slow Query</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button v-if="q.sql.trim().toLowerCase().startsWith('select')" @click="explainQuery(q.sql)" class="px-2 py-1 bg-cyan-900/60 hover:bg-cyan-800 border border-cyan-800/60 rounded text-[11px] font-bold text-cyan-200 transition-colors">
                                            🔬 EXPLAIN
                                        </button>
                                        <button v-if="q.sql.trim().toLowerCase().startsWith('select')" @click="openInPlayground(q.sql)" class="px-2 py-1 bg-slate-900/60 hover:bg-slate-800 border border-slate-800 rounded text-[11px] font-bold text-slate-200 transition-colors">
                                            📝 Playground
                                        </button>
                                    </div>
                                </div>
                                <pre class="text-xs font-mono bg-slate-900/40 p-3 rounded text-slate-300 overflow-x-auto whitespace-pre-wrap select-all leading-relaxed">{{ q.sql }}</pre>
                                <div v-if="q.fileLine" class="text-[10px] text-slate-500 font-mono mt-2">
                                    📍 Source: {{ q.fileLine }}
                                </div>
                            </div>
                        </div>

                        <!-- EXPLAIN Results Panel -->
                        <div v-if="selectedQueryToExplain" class="mb-8 border border-slate-800 rounded-lg bg-slate-950 overflow-hidden">
                            <div class="bg-cyan-955/40 px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-cyan-400">🔬 EXPLAIN Query Plan Analysis</h4>
                                <button @click="selectedQueryToExplain = null" class="text-slate-500 hover:text-slate-300 text-xs">✕ Close</button>
                            </div>
                            <div class="p-4">
                                <pre class="text-xs font-mono bg-slate-900/60 p-2 rounded text-slate-400 mb-4 whitespace-pre-wrap">{{ selectedQueryToExplain }}</pre>
                                <div v-if="explainLoading" class="text-slate-500 text-xs py-4 text-center">Running EXPLAIN analysis...</div>
                                <div v-else-if="errorMessage && !explainResult" class="text-rose-400 text-xs font-mono p-3 bg-rose-955/20 border border-rose-900/60 rounded-lg">
                                    {{ errorMessage }}
                                </div>
                                <div v-else-if="explainResult" class="overflow-x-auto">
                                    <table class="w-full text-left text-[11px] font-mono whitespace-nowrap text-slate-300">
                                        <thead class="bg-slate-900 text-slate-400 font-bold border-b border-slate-800">
                                            <tr>
                                                <th class="px-3 py-2" v-for="(val, key) in explainResult[0]">{{ key }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800">
                                            <tr v-for="row in explainResult" class="hover:bg-slate-900/40">
                                                <td class="px-3 py-2" v-for="val in row">{{ val === null ? 'NULL' : val }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- SQL Playground -->
                        <div id="sql-playground" class="border border-slate-800 rounded-lg bg-slate-950 overflow-hidden">
                            <div class="bg-slate-900/60 px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">📝 Live Raw SQL Query Playground</h4>
                                <span class="text-[10px] text-amber-500 font-bold bg-amber-955/40 border border-amber-800/40 px-2 py-0.5 rounded">🔒 SELECT ONLY</span>
                            </div>
                            <div class="p-4">
                                <div class="mb-4">
                                    <textarea v-model="playgroundQuery" placeholder="SELECT * FROM users LIMIT 5;" rows="4" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all"></textarea>
                                </div>
                                <div class="flex justify-end mb-4">
                                    <button @click="executePlayground" :disabled="playgroundLoading" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-xs font-bold text-white rounded-lg shadow-lg transition duration-150">
                                        {{ playgroundLoading ? 'Executing...' : '⚡ Run Query' }}
                                    </button>
                                </div>

                                <div v-if="playgroundLoading" class="text-slate-500 text-xs py-4 text-center">Executing sandbox query...</div>
                                <div v-else-if="errorMessage && !playgroundResult" class="text-rose-400 text-xs font-mono p-3 bg-rose-955/20 border border-rose-900/60 rounded-lg">
                                    {{ errorMessage }}
                                </div>
                                <div v-else-if="playgroundResult" class="overflow-x-auto">
                                    <div v-if="playgroundResult.length === 0" class="text-slate-500 text-xs py-4 text-center">Query completed. Empty result set.</div>
                                    <table v-else class="w-full text-left text-[11px] font-mono whitespace-nowrap text-slate-300">
                                        <thead class="bg-slate-900 text-slate-400 font-bold border-b border-slate-800">
                                            <tr>
                                                <th class="px-3 py-2" v-for="(val, key) in playgroundResult[0]">{{ key }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800">
                                            <tr v-for="row in playgroundResult" class="hover:bg-slate-900/40">
                                                <td class="px-3 py-2" v-for="val in row">{{ val === null ? 'NULL' : val }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outgoing Mail Tab -->
                    <div v-if="activeTab === 'mail' && selectedLog.emails && selectedLog.emails.length > 0" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <div class="space-y-6">
                            <div v-for="(email, eIdx) in selectedLog.emails" :key="eIdx" class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950">
                                <div class="bg-slate-900/60 p-4 border-b border-slate-800 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs font-mono">
                                    <div>
                                        <div class="text-slate-500 font-semibold">Subject</div>
                                        <div class="text-slate-200 font-bold text-sm mt-0.5">{{ email.subject }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500 font-semibold">To Address</div>
                                        <div class="text-slate-300 mt-0.5">{{ email.to }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500 font-semibold">From Address</div>
                                        <div class="text-slate-300 mt-0.5">{{ email.from }}</div>
                                    </div>
                                </div>
                                <div class="p-4 bg-white text-slate-900 min-h-[300px] border-b border-slate-800 overflow-auto">
                                    <div v-html="email.body"></div>
                                </div>
                                <div v-if="email.link" class="bg-slate-900/40 px-4 py-2 text-xs text-slate-500 font-mono">
                                    🔗 Local Draft Attachment: <span class="select-all text-slate-300">{{ email.link }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Memory Allocation & SVG Flame-Graph Tab -->
                    <div v-if="activeTab === 'memory'" class="flex-1 p-6 overflow-auto bg-slate-950/40">
                        <h3 class="text-base font-bold text-slate-200 mb-4">📊 Live PHP Memory Allocation Flame-Graph</h3>

                        <div v-if="!selectedLog.memory_profile" class="border border-slate-800 rounded-lg p-8 bg-slate-950 text-center text-slate-500 text-sm">
                            No memory profile data found. Newer requests will include full allocation breakdowns automatically.
                        </div>
                        
                        <div v-else class="border border-slate-800 rounded-lg p-6 bg-slate-950 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-xs text-slate-400 font-mono">
                                    Real PHP heap allocation layers (weighted by active subsystem activity)
                                </div>
                                <div class="text-xs font-mono space-x-4 flex">
                                    <span class="text-slate-400">Peak: <span class="text-white font-bold">{{ selectedLog.memory_profile.total_mb }} MB</span></span>
                                    <span class="text-slate-400">Current: <span class="text-cyan-400 font-bold">{{ selectedLog.memory_profile.current_mb }} MB</span></span>
                                </div>
                            </div>
                            
                            <div class="flex flex-col space-y-2 font-mono text-xs mb-6">
                                <div v-for="(layer, lIdx) in selectedLog.memory_profile.layers" :key="lIdx"
                                    :class="{
                                        'bg-rose-955/40 border-rose-800 text-rose-300': layer.color === 'rose',
                                        'bg-amber-955/40 border-amber-800 text-amber-300': layer.color === 'amber',
                                        'bg-cyan-955/40 border-cyan-800 text-cyan-300': layer.color === 'cyan',
                                        'bg-purple-955/40 border-purple-800 text-purple-300': layer.color === 'purple',
                                        'bg-slate-900/40 border-slate-700 text-slate-300': !['rose','amber','cyan','purple'].includes(layer.color)
                                    }"
                                    class="border p-4 rounded-lg flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <span>{{ layer.icon }}</span>
                                        <span class="font-bold">{{ layer.label }}</span>
                                    </div>
                                    <span>{{ layer.mb }} MB ({{ layer.pct }}% of heap)</span>
                                </div>
                            </div>
                            
                            <div class="h-10 w-full rounded-lg overflow-hidden flex shadow-lg border border-slate-800">
                                <div v-for="(layer, lIdx) in selectedLog.memory_profile.layers" :key="lIdx"
                                    :class="{
                                        'bg-rose-600': layer.color === 'rose',
                                        'bg-amber-600': layer.color === 'amber',
                                        'bg-cyan-600': layer.color === 'cyan',
                                        'bg-purple-600': layer.color === 'purple',
                                        'bg-slate-600': !['rose','amber','cyan','purple'].includes(layer.color)
                                    }"
                                    class="h-full text-[10px] text-white flex items-center justify-center font-bold transition-all duration-500"
                                    :style="{ width: layer.pct + '%' }">
                                    {{ layer.pct }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outgoing HTTP Client Mock Interceptor Tab -->
                    <div v-if="activeTab === 'mocks'" class="flex-1 p-6 overflow-auto bg-slate-950/40 text-xs font-mono">
                        <h3 class="text-base font-bold text-slate-200 mb-4 font-sans">🎭 Outgoing Guzzle/HTTP Client Request Mocks</h3>
                        
                        <div class="border border-slate-800 rounded-lg p-6 bg-slate-950 mb-6 font-sans">
                            <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-4">Add Outgoing Request Interception Mock Rule</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-slate-400 font-semibold mb-1 text-[11px]">URL Path Pattern (e.g. *api.stripe.com* or *github*)</label>
                                    <input v-model="newMockUrl" placeholder="*stripe.com*" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-slate-200 focus:outline-none focus:border-red-500 font-mono" />
                                </div>
                                <div>
                                    <label class="block text-slate-400 font-semibold mb-1 text-[11px]">Mock HTTP Response Status Code</label>
                                    <input v-model.number="newMockStatus" type="number" placeholder="200" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-slate-200 focus:outline-none focus:border-red-500 font-mono" />
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-slate-400 font-semibold mb-1 text-[11px]">Response JSON Payload Body</label>
                                <textarea v-model="newMockBody" rows="4" placeholder='{"id": "ch_123", "object": "charge"}' class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-slate-200 focus:outline-none focus:border-red-500 font-mono"></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button @click="addMockRule" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-xs font-bold text-white rounded-lg shadow-md transition duration-150">
                                    ➕ Save Interceptor Rule
                                </button>
                            </div>
                        </div>

                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950">
                            <div class="bg-slate-900/60 px-4 py-3 border-b border-slate-800 font-sans font-bold text-slate-200 uppercase tracking-wider text-[11px]">
                                Active Interceptor Mocking Rules ({{ mocksList.length }})
                            </div>
                            <div v-if="mocksList.length === 0" class="p-8 text-center text-slate-500 font-sans">
                                No active faked endpoints config. Add a pattern above to mock Guzzle/HTTP Client calls.
                            </div>
                            <div v-else class="divide-y divide-slate-800">
                                <div v-for="(m, mIdx) in mocksList" :key="mIdx" class="p-4 flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs bg-purple-955/60 border border-purple-800 text-purple-300 px-2 py-0.5 rounded font-bold">{{ m.status }}</span>
                                            <span class="text-slate-200 font-bold select-all">{{ m.url_pattern }}</span>
                                        </div>
                                        <pre class="text-[10px] text-slate-400 bg-slate-900/60 p-2.5 rounded border border-slate-900 mt-2 max-w-lg overflow-x-auto">{{ JSON.stringify(m.response_body, null, 2) }}</pre>
                                    </div>
                                    <button @click="deleteMockRule(mIdx)" class="text-rose-500 hover:text-rose-400 font-bold px-2 py-1 text-xs transition duration-150">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PHPUnit Test Coverage Runner Tab -->
                    <div v-if="activeTab === 'tests'" class="flex-1 p-6 overflow-auto bg-slate-950/40 font-mono text-xs">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-slate-200 font-sans">🧪 Browser-Based PHPUnit Test Coverage Runner</h3>
                            <button @click="runTestSuite" :disabled="testsLoading" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-xs font-bold text-white rounded-lg shadow-lg font-sans transition duration-150">
                                {{ testsLoading ? '⏳ Running Suite...' : '⚡ Run Local Test Suite' }}
                            </button>
                        </div>
                        
                        <div class="border border-slate-800 rounded-lg overflow-hidden bg-slate-950 shadow-2xl flex flex-col min-h-[450px]">
                            <div class="bg-slate-900 px-4 py-3 border-b border-slate-800 flex items-center justify-between select-none">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider ml-2 font-sans">Bash Console Target (vendor/bin/phpunit)</span>
                                </div>
                                <span v-if="testsExitCode !== null" :class="testsExitCode === 0 ? 'text-green-400 bg-green-950/40 border border-green-800/40' : 'text-rose-400 bg-rose-955/20 border border-rose-900/60'" class="text-[10px] font-bold px-2 py-0.5 rounded font-sans">
                                    Exit Code: {{ testsExitCode }}
                                </span>
                            </div>
                            
                            <div class="p-5 flex-1 overflow-auto bg-slate-950 text-slate-300 font-mono text-[11px] leading-relaxed whitespace-pre-wrap select-all">
                                <div v-if="testsLoading" class="text-slate-500 flex items-center justify-center h-[350px]">
                                    <span class="text-xl mr-2 animate-spin">🔄</span>
                                    <span>Running PHPUnit verification feature tests suite in local background context...</span>
                                </div>
                                <div v-else-if="!testsOutput" class="text-slate-500 flex items-center justify-center h-[350px] font-sans">
                                    Press the button above to execute feature tests and view coverage reports instantly.
                                </div>
                                <div v-else>
                                    {{ testsOutput }}
                                </div>
                            </div>
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
@endverbatim

    <script>
        const { createApp, ref, computed, onMounted, onUnmounted } = Vue;

        createApp({
            setup() {
                const logs = ref([]);
                const selectedLog = ref(null);
                const search = ref('');
                const tagFilter = ref('');
                const isPolling = ref(true);
                let pollInterval = null;
                let sseSource = null;

                const activeTab = ref('log');

                const explainResult = ref(null);
                const playgroundResult = ref(null);
                const explainLoading = ref(false);
                const playgroundLoading = ref(false);
                const playgroundQuery = ref('');
                const selectedQueryToExplain = ref(null);
                const errorMessage = ref('');

                // Mocks controller state
                const mocksList = ref([]);
                const newMockUrl = ref('');
                const newMockStatus = ref(200);
                const newMockBody = ref('{}');

                // PHPUnit test runner state
                const testsLoading = ref(false);
                const testsOutput = ref('');
                const testsExitCode = ref(null);

                const fetchMocks = async () => {
                    try {
                        const res = await fetch('/_agent_debug/mocks');
                        mocksList.value = await res.json();
                    } catch (e) {
                        console.error('Could not fetch mocks', e);
                    }
                };

                const saveMocks = async () => {
                    try {
                        await fetch('/_agent_debug/mock-save', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify({ mocks: mocksList.value })
                        });
                    } catch (e) {
                        console.error('Could not save mocks', e);
                    }
                };

                const addMockRule = async () => {
                    if (!newMockUrl.value.trim()) {
                        alert('Please enter a URL pattern.');
                        return;
                    }
                    let parsedBody = {};
                    try {
                        parsedBody = JSON.parse(newMockBody.value || '{}');
                    } catch (e) {
                        alert('Response body must be valid JSON.');
                        return;
                    }
                    mocksList.value.push({
                        url_pattern: newMockUrl.value.trim(),
                        status: newMockStatus.value || 200,
                        response_body: parsedBody,
                        headers: {}
                    });
                    newMockUrl.value = '';
                    newMockStatus.value = 200;
                    newMockBody.value = '{}';
                    await saveMocks();
                };

                const deleteMockRule = async (idx) => {
                    mocksList.value.splice(idx, 1);
                    await saveMocks();
                };

                const runTestSuite = async () => {
                    testsLoading.value = true;
                    testsOutput.value = '';
                    testsExitCode.value = null;
                    try {
                        const res = await fetch('/_agent_debug/run-tests', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        const data = await res.json();
                        testsOutput.value = data.output || '';
                        testsExitCode.value = data.exit_code ?? null;
                    } catch (e) {
                        testsOutput.value = 'Error running tests: ' + e.message;
                        testsExitCode.value = 1;
                    } finally {
                        testsLoading.value = false;
                    }
                };

                const explainQuery = async (sql) => {
                    explainLoading.value = true;
                    errorMessage.value = '';
                    explainResult.value = null;
                    selectedQueryToExplain.value = sql;
                    try {
                        const response = await fetch('/_agent_debug/explain', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify({ sql })
                        });
                        const data = await response.json();
                        if (data.success) {
                            explainResult.value = data.explain;
                        } else {
                            errorMessage.value = data.message;
                        }
                    } catch (e) {
                        errorMessage.value = e.message;
                    } finally {
                        explainLoading.value = false;
                    }
                };

                const executePlayground = async () => {
                    playgroundLoading.value = true;
                    errorMessage.value = '';
                    playgroundResult.value = null;
                    try {
                        const response = await fetch('/_agent_debug/playground', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify({ sql: playgroundQuery.value })
                        });
                        const data = await response.json();
                        if (data.success) {
                            playgroundResult.value = data.results;
                        } else {
                            errorMessage.value = data.message;
                        }
                    } catch (e) {
                        errorMessage.value = e.message;
                    } finally {
                        playgroundLoading.value = false;
                    }
                };

                const openInPlayground = (sql) => {
                    playgroundQuery.value = sql;
                    playgroundResult.value = null;
                    errorMessage.value = '';
                    activeTab.value = 'database';
                    setTimeout(() => {
                        document.querySelector('#sql-playground')?.scrollIntoView({ behavior: 'smooth' });
                    }, 200);
                };

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

                const startSSE = () => {
                    if (typeof EventSource !== 'undefined') {
                        stopSSE();
                        sseSource = new EventSource('/_agent_debug/sse');
                        sseSource.onmessage = (event) => {
                            try {
                                const newLogs = JSON.parse(event.data);
                                logs.value = newLogs;
                                if (newLogs.length > 0 && !selectedLog.value) {
                                    selectedLog.value = newLogs[0];
                                }
                            } catch (e) {
                                console.error('SSE parsing error:', e);
                            }
                        };
                    }
                };

                const stopSSE = () => {
                    if (sseSource) {
                        sseSource.close();
                        sseSource = null;
                    }
                };

                const startPolling = () => {
                    if (typeof EventSource !== 'undefined') {
                        startSSE();
                    } else {
                        pollInterval = setInterval(fetchLogs, 2000);
                    }
                };

                const stopPolling = () => {
                    stopSSE();
                    if (pollInterval) {
                        clearInterval(pollInterval);
                        pollInterval = null;
                    }
                };

                const runConsole = async (command) => {
                    try {
                        const res = await fetch(`/_agent_debug/artisan/${command}`, { method: 'POST' });
                        const data = await res.json();
                        alert(data.message || 'Artisan command completed successfully!');
                        fetchLogs();
                    } catch (e) {
                        alert(`Error executing artisan console command: ${e}`);
                    }
                };

                const copyToClipboard = (text) => {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text);
                    } else {
                        const el = document.createElement('textarea');
                        el.value = text;
                        document.body.appendChild(el);
                        el.select();
                        document.execCommand('copy');
                        document.body.removeChild(el);
                    }
                };

                const copyAsCurl = (log) => {
                    const method = log.method;
                    const url = log.url;
                    let cmd = `curl -X ${method} "${window.location.origin}${url}"`;
                    if (log.raw) {
                        const lines = log.raw.split("\n");
                        lines.forEach(line => {
                            if (line.includes("Accept:") || line.includes("Accept-Language:") || line.includes("User-Agent:")) {
                                const clean = line.replace(/^\s*\*\s*/, '').trim();
                                const parts = clean.split(": ");
                                if (parts.length >= 2) {
                                    cmd += ` -H "${parts[0]}: ${parts.slice(1).join(': ')}"`;
                                }
                            }
                        });
                    }
                    copyToClipboard(cmd);
                    alert("📋 cURL command copied to clipboard!");
                };

                const getDbTotalTime = (log) => {
                    if (!log.queries_list) return 0;
                    return log.queries_list.reduce((sum, q) => sum + parseFloat(q.time), 0).toFixed(2);
                };

                const getAppOverhead = (log) => {
                    const total = parseFloat(log.duration);
                    const db = parseFloat(getDbTotalTime(log));
                    const overhead = total - db;
                    return (overhead > 0 ? overhead : 0).toFixed(2);
                };

                const getPercent = (value, total) => {
                    if (!total || total <= 0) return 0;
                    const pct = (parseFloat(value) / parseFloat(total)) * 100;
                    return Math.min(100, Math.max(0, Math.round(pct)));
                };

                onMounted(() => {
                    fetchLogs();
                    startPolling();
                    fetchMocks();
                });

                onUnmounted(() => {
                    stopPolling();
                });

                const filteredLogs = computed(() => {
                    let items = logs.value;
                    if (search.value) {
                        const query = search.value.toLowerCase();
                        items = items.filter(log => 
                            log.url.toLowerCase().includes(query) || 
                            log.method.toLowerCase().includes(query) || 
                            log.status.toString().includes(query)
                        );
                    }
                    if (tagFilter.value) {
                        const query = tagFilter.value.toLowerCase();
                        items = items.filter(log => 
                            log.debug_tag && log.debug_tag.toLowerCase().includes(query)
                        );
                    }
                    return items;
                });

                const timelineWaterfall = computed(() => {
                    if (!selectedLog.value) return [];
                    const list = [];
                    const total = parseFloat(selectedLog.value.duration) || 1.0;
                    
                    const bootDur = parseFloat(getAppOverhead(selectedLog.value));
                    list.push({
                        name: 'App Booting & Core Routing',
                        type: 'boot',
                        duration: bootDur,
                        start: 0,
                        color: 'bg-cyan-500',
                        textClass: 'text-cyan-400'
                    });
                    
                    let runningOffset = bootDur;
                    
                    if (selectedLog.value.queries_list) {
                        selectedLog.value.queries_list.forEach((q, idx) => {
                            const dur = parseFloat(q.time) || 0.0;
                            list.push({
                                name: `SQL: ${q.sql.substring(0, 80)}${q.sql.length > 80 ? '...' : ''}`,
                                type: 'db',
                                duration: dur,
                                start: runningOffset,
                                color: 'bg-green-500',
                                textClass: 'text-green-400'
                            });
                            runningOffset += dur;
                        });
                    }
                    
                    if (selectedLog.value.spans) {
                        selectedLog.value.spans.forEach((s) => {
                            const dur = parseFloat(s.duration) || 0.0;
                            list.push({
                                name: `Span: ${s.name}`,
                                type: 'span',
                                duration: dur,
                                start: runningOffset,
                                color: 'bg-purple-500',
                                textClass: 'text-purple-400'
                            });
                            runningOffset += dur;
                        });
                    }
                    
                    if (selectedLog.value.http_requests) {
                        selectedLog.value.http_requests.forEach((h) => {
                            const dur = parseFloat(h.duration) || 0.0;
                            list.push({
                                name: `External API: [${h.method}] ${h.url}`,
                                type: 'http',
                                duration: dur,
                                start: runningOffset,
                                color: 'bg-orange-500',
                                textClass: 'text-orange-400'
                            });
                            runningOffset += dur;
                        });
                    }
                    
                    return list.map(item => {
                        const startPct = Math.min(95, Math.max(0, (item.start / total) * 100));
                        const durPct = Math.min(100 - startPct, Math.max(1, (item.duration / total) * 100));
                        return {
                            ...item,
                            startPct: startPct.toFixed(2),
                            durPct: durPct.toFixed(2)
                        };
                    });
                });

                const highlightedContent = computed(() => {
                    if (!selectedLog.value) return '';
                    let text = selectedLog.value.raw;
                    
                    text = text
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;");

                    text = text.replace(/(⚠️ WARNING:.*)/g, '<span class="text-yellow-400 font-bold bg-yellow-950/40 px-1 py-0.5 rounded border border-yellow-800/40">$1</span>');
                    text = text.replace(/(👉 Solution:.*)/g, '<span class="text-green-400 font-bold bg-green-950/40 px-1 py-0.5 rounded">$1</span>');

                    text = text.replace(/(select \* from|insert into|update|delete from)/gi, '<span class="text-green-400 font-semibold">$1</span>');

                    text = text.replace(/(DB::beginTransaction\(\))/g, '<span class="text-cyan-400 font-bold">$1</span>');
                    text = text.replace(/(DB::commit\(\))/g, '<span class="text-emerald-400 font-bold">$1</span>');
                    text = text.replace(/(DB::rollBack\(\))/g, '<span class="text-rose-400 font-bold">$1</span>');

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
                    tagFilter,
                    isPolling,
                    filteredLogs,
                    highlightedContent,
                    fetchLogs,
                    selectLog,
                    togglePolling,
                    getMethodClass,
                    getStatusClass,
                    formatTime,
                    runConsole,
                    copyToClipboard,
                    copyAsCurl,
                    explainResult,
                    playgroundResult,
                    explainLoading,
                    playgroundLoading,
                    playgroundQuery,
                    selectedQueryToExplain,
                    errorMessage,
                    explainQuery,
                    executePlayground,
                    openInPlayground,
                    getDbTotalTime,
                    getAppOverhead,
                    getPercent,
                    timelineWaterfall,
                    mocksList,
                    newMockUrl,
                    newMockStatus,
                    newMockBody,
                    addMockRule,
                    deleteMockRule,
                    testsLoading,
                    testsOutput,
                    testsExitCode,
                    runTestSuite
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
