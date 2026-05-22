# Core Diagnostics & Profilers Reference Manual

**Laravel Agent-Debugger v3.1.1** provides **13 interactive dashboard tabs** and **29 premium server-driven diagnostic profilers** designed to catch errors, identify optimization opportunities, and simplify local development — with zero external JS/CSS dependencies and fully offline capabilities.

---

## 📋 Interactive Dashboard Tabs

The debugger interface provides **13 unified tabs** to monitor application state changes dynamically:

### 1. 📜 Execution Log
*   **Description**: Display of active request execution records, actor details, runtime timings, HTTP headers, request variables, and exception stack traces.
*   **Telemetry Captured**: HTTP Method, URL, Referer, Client IP, Authenticated User details, Status Code, and Execution Duration.
*   **Usage**: The default entry point when analyzing any HTTP request or API call.

### 2. 🗃️ Database Queries
*   **Description**: Display of all executed SQL statements with caller traces (class/file line), N+1 loop flags, duplicate markers, index advisory tips, and one-click `🔬 Explain` plan mapping.
*   **Telemetry Captured**: Raw SQL, bound parameters, execution duration in milliseconds, and the originating PHP file path and line number.
*   **Usage**: Essential for diagnosing slow queries, N+1 query loops, or verifying transaction rollbacks.

### 3. ⏱️ Timeline Waterfall
*   **Description**: Proportional Gantt chart sequencing App Boot, database query segments, outgoing HTTP requests, and manual milestone timings (`debug_span()`).
*   **Telemetry Captured**: Proportional segment offsets and durations relative to the overall request execution lifecycle.
*   **Usage**: Instantly exposes what percentage of total request latency is spent on database operations vs. application logic.

### 4. 📢 Events & Jobs
*   **Description**: Real-time logging of dispatched events, queued background job signatures, and serialized data payloads.
*   **Telemetry Captured**: Event class name, listener classes, queued job class, target queue, and serialized parameters.
*   **Usage**: Verifies background event dispatch chains and asynchronous job scheduling.

### 5. 🗺️ Blade Views
*   **Description**: Dynamic view composition hierarchy flowchart mapping layout, component, and template renderings.
*   **Telemetry Captured**: Rendered view paths, layouts, blade components, and compiled file paths.
*   **Usage**: Simplifies UI debugging in deeply nested template layouts.

### 6. 📦 Inertia Properties
*   **Description**: Interactive JSON tree viewer showing the exact `props` payloads passed to Inertia.js views.
*   **Telemetry Captured**: Complete props collection variables, including model attributes and computed variables.
*   **Usage**: Prevents print-debugging massive arrays in Vue or React components.

### 7. 🔌 Livewire Properties
*   **Description**: Complete telemetry list of Livewire component fingerprints, updates, serverMemo state, and execution parameters.
*   **Telemetry Captured**: Livewire update payloads, state updates, validation errors, and method calls.
*   **Usage**: Solves state syncing issues in Livewire components during form submissions.

### 8. 🗂️ Cache Monitor
*   **Description**: Live breakdown of cache hits, misses, writes, and forgetting keys, along with data sizes and TTL details.
*   **Telemetry Captured**: Cache operations (`get`, `set`, `forget`), keys, byte sizes, and remaining expiration.
*   **Usage**: Audits application cache usage efficiency.

### 9. 🔄 Eloquent Events
*   **Description**: Active monitoring card listing observer calls (`creating`, `created`, `updating`, `updated`, `deleting`, `deleted`) on models.
*   **Telemetry Captured**: Eloquent model name, primary key, and active database event name.
*   **Usage**: Traces side-effects triggered by model observers or listeners.

### 10. 📧 Outgoing Mail
*   **Description**: Renders exact HTML/Markdown preview blocks of outgoing Mailables sandbox.
*   **Telemetry Captured**: Email sender, recipient, subject, headers, and full HTML/text body contents.
*   **Usage**: Intercepts Mailables locally without requiring third-party mail sandboxes.

### 11. 📊 Memory Allocations
*   **Description**: Stacked-bar flame-graph displaying dynamic PHP heap allocations (Boot, Eloquent, HTTP, GC).
*   **Telemetry Captured**: Proportional peak memory consumed during application boot, DB execution, and output rendering.
*   **Usage**: Identifies memory leaks and heavy model instances.

### 12. 🎭 Outgoing API Mocks
*   **Description**: Visual rule builder allowing developers to map endpoint stubs to Guzzle/HTTP Client mocks (`Http::fake()`).
*   **Telemetry Captured**: URL patterns, JSON response templates, HTTP status codes, and mock validation status.
*   **Usage**: Mocks external payment gateways or third-party APIs during local sandbox testing.

### 13. 🧪 PHPUnit Runner
*   **Description**: Interactive dark terminal console showing execution progress and exit statuses of local PHPUnit suites.
*   **Telemetry Captured**: Live stream of PHPUnit progress characters, assertion counts, failures, and final exit status.
*   **Usage**: Run your project's test suite directly from the browser window.

---

## Phase A: Core Diagnostics

### 1. Zero-JS Floating Viewport Status Badge 🔴
*   **Description**: A server-side HTML/CSS injection that renders a glassmorphic floating status badge in the bottom-right corner of HTML responses. Shows real-time execution duration, memory usage, and active query count at a glance.
*   **Under the Hood**: `ViewportBorderInjector` middleware intercepts the final HTTP response. If the response header is `text/html` and is not an AJAX/Inertia/Livewire request, it injects custom CSS styling and a small HTML container right before `</body>`.
*   **Log Output Reference**:
    ```html
    <div id="agent-debug-badge" style="position: fixed; bottom: 16px; right: 16px; backdrop-filter: blur(8px)...">
      Duration: 114ms | Memory: 14.2MB | Queries: 6
    </div>
    ```

### 2. User Navigation Breadcrumbs 🧭
*   **Description**: Tracks the user's session navigation history — last 10 visited URLs, HTTP methods, and status codes — to reconstruct what path led to an error.
*   **Under the Hood**: `SessionBreadcrumbs` reads and writes to a specific session array key `agent_debugger_breadcrumbs`. On every incoming request, it appends the current request metadata and truncates the array if it exceeds the configuration limit.
*   **Log Output Reference**:
    ```text
    - SESSION BREADCRUMB TRAIL:
      1. [GET 200] /student/dashboard
      2. [GET 200] /student/tests/4/instructions
      3. [POST 500] /student/tests/4/submit (CRASHED)
    ```

### 3. Caught Exceptions Tracker 🪝
*   **Description**: Intercepts exceptions that are caught and silently handled (or redirected) inside `try-catch` blocks, preventing silent bugs from vanishing.
*   **Under the Hood**: Subscribes to Laravel's global log listener channel. When an exception is logged via `Log::error()` or `report()`, it extracts the exception class, stack trace, and message, saving them to the current request container.
*   **Log Output Reference**:
    ```text
    Class: Illuminate\Database\Eloquent\ModelNotFoundException
    Message: No query results for model [App\Models\Book] #42
    File: BookController.php | Line: 74
    ```

### 4. Database Transaction Auditor 💳
*   **Description**: Logs `beginTransaction`, `commit`, and `rollBack` events inline within the SQL query sequence, pointing to exact file/line triggers.
*   **Under the Hood**: Listens to database transaction events (`TransactionBeginning`, `TransactionCommitted`, `TransactionRolledBack`) and logs the actions alongside the originating backtrace caller.
*   **Log Output Reference**:
    ```text
    * [0.00ms] DB::beginTransaction()
    * [1.02ms] select * from `users` where `id` = 14 limit 1
    * [0.00ms] DB::rollBack() (Triggered on BookController@save line 102)
    ```

### 5. N+1 Query Loop Detector ⚠️
*   **Description**: Parameterizes SQL queries into templates. If any template repeats more than 5 times in a single request, it logs an explicit warning block with eager-loading solutions.
*   **Under the Hood**: `QueryProfiler` normalizes SQL strings by replacing bound parameters with placeholders (`?`), counting the occurrences of each unique query signature.
*   **Log Output Reference**:
    ```text
    ⚠️ WARNING: N+1 Query Detected! The following query executed 12 times:
    * select * from `questions` where `id` = ? limit 1
    👉 Solution: Eager load relationships (e.g. TestAttempt::with('questions')) in your controller.
    ```

### 6. Config & Env Drift Detector 🔄
*   **Description**: Hashes monitored `.env` keys at the start of a request. If the hash mismatches the previous request's signature, it logs a config drift warning.
*   **Under the Hood**: `EnvironmentTracker` computes a SHA-256 checksum of specified `.env` keys (e.g. `SESSION_DRIVER`, `DB_CONNECTION`), caches it, and performs comparisons on subsequent requests.
*   **Log Output Reference**:
    ```text
    ⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!
      - SESSION_DRIVER changed from 'file' to 'redis'
      - QUEUE_CONNECTION changed from 'sync' to 'database'
    ```

### 7. Compiled Blade Exception Resolver 🖌️
*   **Description**: Resolves compiled Blade view exception paths (e.g. `/storage/framework/views/2fa8d7.php`) back to their original `.blade.php` file path and template line number.
*   **Under the Hood**: `ExceptionProfiler` intercepts `ViewException` errors, inspects the error callstack, and uses standard regular expressions to parse the compiled temporary files, matching them to original templates.
*   **Log Output Reference**:
    ```text
    CRITICAL BLADE COMPILER ERROR:
    File: /resources/views/components/book-card.blade.php | Line: 12
    Original Line: <p>{{ $book->author->getShortName() }}</p>
    Error: Call to a member function getShortName() on null (Authored in controller context)
    ```

### 8. Gate & Policy Authorization Profiler 🛡️
*   **Description**: Subscribes to evaluation hooks to capture every authorization check, its arguments, and the result.
*   **Under the Hood**: Subscribes to the `Gate::after()` callback container to capture the name of the gate, target model instance, and boolean response.
*   **Log Output Reference**:
    ```text
    * [ALLOWED] view-test (Arguments: App\Models\Test #4)
    * [DENIED] update-test (Arguments: App\Models\Test #4)
    ```

### 9. Custom Session State Logger 💾
*   **Description**: Reads all session variables, strips standard Laravel internal tokens, and exposes custom developer session states.
*   **Under the Hood**: `SessionStateProfiler` extracts `Session::all()`, filters keys matching framework defaults (such as `_token`, `_previous`, `_flash`), and appends the rest to the request footprint.
*   **Log Output Reference**:
    ```text
    - ACTIVE SESSION STATE:
      * test_attempt_id => 82
      * active_step => 5
    ```

### 10. Rich Actor Identification 👤
*   **Description**: Resolves the authenticated user ID and displays a configured profile field (like email or name) for instant user context.
*   **Under the Hood**: `DebugActivityLogger` queries the active `Auth::user()` model and reads attributes specified in the configuration array `'auth_identifiers'`.
*   **Log Output Reference**:
    ```text
    Auth: User #14 (student: john@example.com)
    ```

---

## Phase B: Database & Query Intelligence

### 11. Visual SQL EXPLAIN Query Analyzer 🔬
*   **Description**: Runs `EXPLAIN` on database queries and visualizes the database executor strategy directly inside the Queries tab.
*   **Under the Hood**: Fiers an AJAX request containing the select statement to the `/_agent_debug/explain` endpoint, prepending `EXPLAIN ` to the query, running it, and formatting the plans.
*   **Usage**: Click the `🔬 Explain` button next to any database query record inside the dashboard to view detailed table lookup and index usage plans.

### 12. Duplicate & Redundant Query Detector 👥
*   **Description**: Identifies identical queries executed multiple times in the same request, recommending caching solutions.
*   **Under the Hood**: Hashes SQL strings together with their parameter bindings. If a query hash repeats, it is flagged as a duplicate.
*   **Usage**: Highlighted with a warning badge directly under the Database tab with direct remedies.

### 13. Interactive SQL Playground 📝
*   **Description**: A secure, browser-based SQL select playground inside the dashboard.
*   **Under the Hood**: Fiers SQL statement inputs to `/_agent_debug/playground`, parses database connections, and executes requests in a read-only transaction, rolling back automatically.
*   **Usage**: Type queries directly into the Database queries tab playground or click "Send to Playground" next to any logged query to run it manually.

### 14. SQL Database Index Advisor 💾
*   **Description**: Heuristically scans `WHERE`, `JOIN`, and `ORDER BY` query columns to suggest missing indexes.
*   **Under the Hood**: `QueryAnalyzer` evaluates table schemas and columns used in lookups. If a column has no index, it generates copy-pasteable Artisan migrations.
*   **Log Output Reference**:
    ```text
    💡 DATABASE INDEX RECOMMENDATION:
    * Table: 'orders' | Column: 'user_id'
    👉 Solution: Schema::table('orders', fn($t) => $t->index('user_id'));
    ```

---

## Phase C: Async & Side-Effect Telemetry

### 15. Queue Job & Event Payload Serializer 🎧
*   **Description**: Records dispatched events and queued job payloads to trace asynchronous actions.
*   **Under the Hood**: `EventJobProfiler` hooks into event dispatcher channels and queue payload workers, extracting class names and parameters.
*   **Usage**: Rendered inside the dedicated **Events & Jobs** dashboard tab.

### 16. Outgoing Mail Sandbox 📧
*   **Description**: Intercepts Mailables, saves copies as HTML to disk, and displays previews on the dashboard.
*   **Under the Hood**: `MailProfiler` intercepts the `MessageSending` event, extracts message contents, writes HTML files to `storage/logs/agent-debugger/emails`, and indexes them.
*   **Usage**: View exact email outputs inside the **Outgoing Mail** dashboard tab.

### 17. Cache Hit/Miss Monitor 🗂️
*   **Description**: Logs cache actions (gets, writes, forgets) alongside key configurations, data sizes, and TTL.
*   **Under the Hood**: `CacheProfiler` listens to framework cache event hooks (`CacheHit`, `KeyWritten`, `KeyForgotten`).
*   **Usage**: Appears under the **Cache Monitor** tab of request details.

### 18. Eloquent Model Lifecycle Tracker 🔄
*   **Description**: Tracks observers firing database operations (`creating`, `created`, etc.).
*   **Under the Hood**: `EloquentProfiler` registers observer hooks across Eloquent lifecycles, logging primary keys and class names.
*   **Usage**: Appears under the **Eloquent Events** tab.

---

## Phase D: Visual Profiling & Timelines

### 19. DevTools-Style Timeline Waterfall ⏱️
*   **Description**: Proportional Gantt chart visualizing execution segments (Boot, SQL, Outgoing requests).
*   **Under the Hood**: Collects microtime offsets of all tracked events and builds a timeline dataset.
*   **Usage**: Displays at the top of the request review page.

### 20. Interactive Blade Template Composition Tree 🗺️
*   **Description**: Draws layouts, components, and views flowchart per request.
*   **Under the Hood**: `ViewProfiler` hooks into view composings, compiling nesting layouts and rendering orders.
*   **Usage**: Displays inside the **Blade Views** tab.

### 21. Live PHP Memory Allocation Flame-Graph 📊
*   **Description**: Segmented stacked memory visualizer updating live.
*   **Under the Hood**: Samples memory changes at Boot, DB queries, and Response compilation steps.
*   **Usage**: Renders a clean flame-graph inside the **Memory Allocations** tab.

### 22. Outgoing Latency Radar 📊
*   **Description**: Calculates ratios of database lookups vs. app core timings.
*   **Under the Hood**: Sums execution milliseconds of all query items and compares it to overall request time.
*   **Usage**: Displayed as progress indicators in request logs.

---

## Phase E: Environment, Security & DevOps

### 23. Livewire Hydration State Tracker 🔌
*   **Description**: Captures Livewire request parameters and saves state JSON copies.
*   **Under the Hood**: Intercepts Livewire endpoints and dumps payload state variables.
*   **Usage**: Displayed under the **Livewire Properties** tab.

### 24. Dev Environment Configuration Shield 🚨
*   **Description**: TCP socket auditor warning when local dev services are offline.
*   **Under the Hood**: `SystemAuditor` checks connection ports (e.g. 6379 for Redis, 1025 for Mailpit) on app startup.
*   **Usage**: Glowing alert header banners appear in the dashboard if services fail checks.

### 25. Cookie & CSRF Token Debugger 🍪
*   **Description**: Explains form submission failures resulting in 419 errors.
*   **Under the Hood**: Matches CSRF headers against session tokens, logging failures.
*   **Usage**: Compiles analysis reports inside form request error blocks.

### 26. Interactive .env vs .env.example Diff Audit ⚖️
*   **Description**: Color-coded side-by-side comparison of local environment configs.
*   **Under the Hood**: `SystemAuditor` parses files, maps keys, and highlights drifts.
*   **Usage**: Displayed under the Configuration drift section.

### 27. Category Tag Filtering 🏷️
*   **Description**: Filters requests using appended `?_debug_tag=x` query parameters.
*   **Under the Hood**: Scopes session log files matching specified category hashes.
*   **Usage**: Scope request records easily in the dashboard sidebar.

### 28. Composer Security Dependency Auditor 🩹
*   **Description**: Scans vendor packages for security issues using advisory databases.
*   **Under the Hood**: Parses `composer.lock` and matches against CVE index records.
*   **Usage**: Warnings are shown in the configuration dashboard.

### 29. Git Branch Code Correlation Analyzer 🚀
*   **Description**: Connects active request exceptions with local git uncommitted drift.
*   **Under the Hood**: Identifies modified files and matches them to request callstacks.
*   **Usage**: Git status labels appear in the header of execution records.

---

## Dashboard Tools

### 30. Real-time SSE Log Streaming 🚿
*   **Description**: Updates the dashboard feeds in real-time with zero polling overhead.
*   **Under the Hood**: SSE streams logs via the `/_agent_debug/sse` route.

### 31. Outgoing API Mock Interceptor 🎭
*   **Description**: Persists and intercepts HTTP Client requests.
*   **Under the Hood**: Loads mock rules from `mocks.json` and registers them via `Http::fake()`.

### 32. Browser-Based PHPUnit Test Runner 🧪
*   **Description**: Streams test runner output directly to the browser.
*   **Under the Hood**: Executes PHPUnit in a background process and streams output.

### 33. Artisan Quick-Console 🛠️
*   **Description**: Clear caches and clean log directories with header shortcuts.
*   **Under the Hood**: Proxies actions to Laravel's Artisan console backend.
