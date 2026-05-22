# Core Diagnostics & Profilers

**Laravel Agent-Debugger v3.1.0** provides **29 premium server-driven diagnostic profilers** and **3 interactive dashboard tools** designed to catch errors, identify optimization opportunities, and simplify local development — with zero external JS/CSS dependencies.

---

## Phase A: Core Diagnostics

### 1. Zero-JS Floating Viewport Status Badge 🔴

A zero-dependency server-side HTML/CSS injection that renders a glassmorphic floating status badge in the bottom-right corner of HTML responses. Shows real-time execution duration, memory usage, and active query count.

**How it works**: `ViewportBorderInjector` middleware scans the final response. If it's `text/html` and not an AJAX/Inertia request, it injects a styled blur container before `</body>`.

---

### 2. User Navigation Breadcrumbs 🧭

Tracks the user's session navigation history — last 10 visited URLs, HTTP methods, and status codes — to reconstruct what path led to an error.

```text
- SESSION BREADCRUMB TRAIL:
  1. [GET 200] /student/dashboard
  2. [GET 200] /student/tests/4/instructions
  3. [POST 500] /student/tests/4/submit (CRASHED)
```

---

### 3. Caught Exceptions Tracker 🪝

Intercepts exceptions that are silently swallowed inside `try-catch` redirects before they disappear from logs.

```text
Class: Illuminate\Database\Eloquent\ModelNotFoundException
Message: No query results for model [App\Models\Book] #42
File: BookController.php  Line: 74
```

---

### 4. Database Transaction Auditor 💳

Logs `beginTransaction`, `commit`, and `rollBack` events inline within the SQL query sequence, pointing to exact file/line triggers.

```text
* [0.00ms] DB::beginTransaction()
* [1.02ms] select * from `users` where `id` = 14 limit 1
* [0.00ms] DB::rollBack() (Triggered on BookController@save line 102)
```

---

### 5. N+1 Query Loop Detector ⚠️

Parameterizes queries into generic templates. If any template repeats 5+ times in a single request, it fires a warning with copy-pasteable eager loading remedies.

```text
⚠️ WARNING: N+1 Query Detected! The following query executed 12 times:
* select * from `questions` where `id` = ? limit 1
👉 Solution: Eager load relationships (e.g. TestAttempt::with('questions'))
```

---

### 6. Config & Env Drift Detector 🔄

SHA-256 hashes monitored `.env` keys at request start. If the hash mismatches the previous request's cached signature, it diffs and reports changed keys.

```text
⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!
  - SESSION_DRIVER changed from 'file' to 'redis'
```

---

### 7. Compiled Blade Exception Resolver 🖌️

Maps `ViewException` compiled cache paths (e.g., `/storage/framework/views/2fa8d7.php`) back to the physical `.blade.php` source file and exact line.

---

### 8. Gate & Policy Authorization Profiler 🛡️

Subscribes to `Gate::after()` to capture every ability evaluation result, arguments, and allowed/denied outcome.

```text
* [ALLOWED] view-test (Arguments: App\Models\Test #4)
* [DENIED] update-test (Arguments: App\Models\Test #4)
```

---

### 9. Custom Session State Logger 💾

Reads `Session::all()`, filters framework internals, and exposes developer-defined session keys per request.

---

### 10. Rich Actor Identification 👤

Resolves the authenticated User ID to a configured identifier field (email, username) for instant actor context.

```text
Auth: User #14 (student: john@example.com)
```

---

## Phase B: Database & Query Intelligence

### 11. Visual SQL EXPLAIN Query Analyzer 🔬

Adds a `🔬 Explain` button next to each query in the Database tab. Submits via `/_agent_debug/explain`, runs `EXPLAIN` on your database, and renders the query execution plan with highlighted index scans, full table scans, and join types.

---

### 12. Duplicate & Redundant Query Detector 👥

Detects exact SQL queries (same statement + same parameter bindings) running more than once. Flags them with cache-based remediation suggestions.

---

### 13. Interactive SQL Playground 📝

A safe, in-dashboard `SELECT`-only SQL terminal. Results render as a paginated table (capped at 50 rows). Includes a "Send to Playground" link from any logged query.

---

### 14. SQL Database Index Advisor 💾

Heuristically scans `WHERE`, `JOIN`, and `ORDER BY` columns for missing indexes and generates ready-to-paste Artisan migration recommendations.

```text
💡 DATABASE INDEX RECOMMENDATION:
* Table: 'orders' | Column: 'user_id'
👉 Solution: Schema::table('orders', fn($t) => $t->index('user_id'));
```

---

## Phase C: Async & Side-Effect Telemetry

### 15. Queue Job & Event Payload Serializer 🎧

Captures every dispatched application event and background queue job alongside their fully decoded PHP payload arguments. Renders them in the **Events & Jobs** dashboard tab.

---

### 16. Outgoing Mail Sandbox 📧

Hooks into `MessageSending` event, captures email content to disk as `.html` files, and renders the exact visual HTML/Markdown preview inside a dedicated **Outgoing Mail** dashboard tab.

---

### 17. Cache Hit/Miss Monitor 🗂️

Listens to cache event callbacks and records every `Cache::get()`, `Cache::put()`, and `Cache::forget()` with the cache key, data byte size, and TTL expiration.

---

### 18. Eloquent Model Lifecycle Tracker 🔄

Registers a global Eloquent observer capturing `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` events with the model class name and record ID.

---

## Phase D: Visual Profiling & Timelines

### 19. DevTools-Style Timeline Waterfall ⏱️

A proportional horizontal Gantt chart sequencing the full request lifecycle:
- 🔵 **App Boot** — framework bootstrap latency
- 🟢 **SQL Queries** — per-query execution segments
- 🟣 **Custom Spans** — `debug_span()` profiled code blocks
- 🟠 **External HTTP** — outgoing API call durations

---

### 20. Interactive Blade Template Composition Tree 🗺️

Parses the view rendering sequence from logs and draws a connected flowchart showing exactly which layouts, components, and partial views were composed during the request. Color-coded by type:
- 🏛️ **Purple** — layout files
- 🧩 **Cyan** — reusable components
- 📄 **Slate** — page-level templates

---

### 21. Live PHP Memory Allocation Flame-Graph 📊

Uses `memory_get_peak_usage(true)` to capture real PHP heap consumption and segments it into four subsystem layers (Boot, Eloquent, HTTP/Payload, GC Residual), proportionally weighted by active request activity. Rendered as a color-coded stacked bar flame-graph that updates live per request.

---

### 22. Outgoing Latency Radar 📊

Calculates the ratio between total database query time vs. application overhead and renders proportional progress meters in the log view.

---

## Phase E: Environment, Security & DevOps

### 23. Livewire Hydration State Tracker 🔌

Captures Livewire component reactive state from `data.fingerprint` and `data.serverMemo` on update requests and saves discrete `.json` archives to `storage/logs/agent-debugger/livewire/`.

---

### 24. Dev Environment Configuration Shield 🚨

At `php artisan serve` startup, performs fast TCP socket checks:
- If `QUEUE_CONNECTION=redis`, checks if port `6379` is listening
- If `MAIL_MAILER=smtp`, checks if port `1025` (Mailpit/Mailhog) is active

Flashes a glowing alert banner on the dashboard if a configured service is offline.

---

### 25. Cookie & CSRF Token Debugger 🍪

Analyzes request cookie states and CSRF token validation chains, surfacing exactly why a `419 Page Expired` error occurred.

---

### 26. Interactive .env vs .env.example Diff Audit ⚖️

Side-by-side color-coded comparison grid of keys present in `.env` but missing from `.env.example` and vice versa, with drift warnings.

---

### 27. Category Tag Filtering 🏷️

Appending `?_debug_tag=checkout` to any URL tags that request in the dashboard sidebar, enabling scoped filtering of related multi-request workflows.

---

### 28. Composer Security Dependency Auditor 🩹

Scans `composer.lock` against the PHP Security Advisory database, flagging packages with known CVEs directly on the dashboard.

---

### 29. Git Branch Code Correlation Analyzer 🚀

Reads the active Git branch and displays it on every request log. Lists files modified locally (via `git diff --name-only`) with a warning badge when a file in the request callstack has uncommitted changes.

---

## Dashboard Tools

### 30. Real-time SSE Log Streaming 🚿

Upgrades the dashboard from timed AJAX polling to a persistent `EventSource` stream via `/_agent_debug/sse`, delivering zero-lag log updates. Falls back to standard polling automatically if `EventSource` is unavailable.

---

### 31. Outgoing API Mock Interceptor 🎭

A full UI rule builder inside the dashboard's **Outgoing API Mocks** tab. Rules are persisted to `storage/logs/agent-debugger/mocks.json` and applied via `Http::fake()` on every request — intercepting Guzzle/HTTP Client calls without touching application code.

---

### 32. Browser-Based PHPUnit Test Runner 🧪

A dark terminal-styled console inside the **PHPUnit Runner** tab that executes `vendor/bin/phpunit tests/Feature/` in the background and streams the full output directly to the browser, including the final exit code badge.

---

### 33. Artisan Quick-Console 🛠️

Floating header buttons for one-click execution of:
- `php artisan cache:clear`
- `php artisan route:clear`
- `php artisan agent:debug-clean`
