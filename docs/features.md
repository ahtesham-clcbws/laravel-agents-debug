# Core Diagnostics & Profilers

Laravel Agent-Debugger exposes ten premium server-driven diagnostic profilers specifically designed to catch errors, identify optimization opportunities, and simplify local development.

---

## 1. Zero-JS Floating Viewport Status Badge 🔴

### The Problem
When working locally, developers frequently forget if their profiling features are turned on, resulting in unnecessary background calculations or polluted storage logs. Heavy frontend JavaScript solutions clash with SPA framework states.

### The Solution
A zero-dependency server-side HTML/CSS injection that renders a beautiful, glassmorphic floating dashboard status badge in the bottom-right corner of successful HTML responses showing real-time metrics.

### What We Log
This is a pure visual frontend cue. No file logs are written.

### How It Works
The `ViewportBorderInjector` global middleware scans the final response payload. If it matches `text/html`, it dynamically retrieves request duration and active query count from the state manager and injects a styled blur container right before `</body>`:
```html
<div id="agent-debugger-badge" style="position: fixed; bottom: 16px; right: 16px; background: rgba(18, 18, 18, 0.85); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 8px 16px; color: #fff; font-family: sans-serif; font-size: 12px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.4); z-index: 999999;">
    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 8px #ef4444;"></span>
    <strong>Agent Active</strong>
    <span>|</span>
    <span>⚡ 65ms</span>
    <span>|</span>
    <span>🗄️ 12 Queries</span>
</div>
```

---

## 2. User Navigation Breadcrumbs 🧭

### The Problem
Stack traces show a crash at a specific instant, but completely isolate *how* the user got there, making multi-stage forms or wizard redirection flows incredibly hard to trace.

### The Solution
A session-backed, lightweight request history trail that tracks preceding navigation steps.

### What We Log
Logs the last 10 visited URLs with methods and status codes:
```text
- SESSION BREADCRUMB TRAIL:
  1. [GET 200] /student/dashboard
  2. [GET 200] /student/tests/4/instructions
  3. [POST 500] /student/tests/4/submit (CRASHED)
```

### How It Works
At request start, the middleware loads a history array from the active session, pushes the incoming route, trims the collection to 10 records, and saves it. On request termination, it fills in the final HTTP status code.

---

## 3. Caught Exceptions Tracker 🪝

### The Problem
Newbies frequently swallow failures in manual `try-catch` redirects:
```php
try {
    $book = Book::findOrFail($id);
} catch (\Exception $e) {
    return redirect('/home'); // Silent failure!
}
```
The page redirects successfully, but the database fail remains hidden without creating system error logs.

### The Solution
A proactive Exception Interceptor that traps exceptions thrown during the request lifecycle before they get cleared by user-level controllers or redirects.

### What We Log
SWallowed exception class names, messages, files, and lines:
```text
Class: Illuminate\Database\Eloquent\ModelNotFoundException
Message: No query results found for model [App\Models\Book] #42
Line: 74
```

### How It Works
We listen to the native log writing channel using `Log::listen()`. If a redirection response (302) is compiled and warnings have been recorded, the profiler extracts the caught exception payload and appends it to the diagnostic log.

---

## 4. Database Transaction Auditor 💳

### The Problem
Payment gateways or background jobs use transactions. If a minor database constraint occurs, a silent `DB::rollBack()` is triggered. The request completes successfully, but the database remains empty. A junior developer has no idea why the code ran but data was not saved.

### The Solution
An inline database transaction auditor logging Begins, Commits, and Rollbacks interleaved directly within the SQL query sequence.

### What We Log
Exact timestamps and file locations of transaction actions:
```text
* [0.00ms] DB::beginTransaction()
* [1.02ms] select * from `users` where `id` = 14 limit 1
* [0.00ms] DB::rollBack() (Triggered on BookController@save line 102)
```

### How It Works
The database profiler registers listeners for framework events: `TransactionBeginning`, `TransactionCommitted`, and `TransactionRolledBack`. It inspects PHP's debug callstack to locate the file and line triggering the transaction action.

---

## 5. N+1 Query Loop Detector ⚠️

### The Problem
Developers load list models and request relationships inside Blade loops without eager loading, generating hundreds of unnecessary database queries that slow down the server.

### The Solution
An automated query pattern signature scanner. If an identical query pattern runs 5+ times in a single request, it flags it as a warning with immediate eager loading advice.

### What We Log
An N+1 alert listing execution count and explicit code solutions:
```text
⚠️ WARNING: N+1 Query Detected! The following query executed 12 times:
* select * from `questions` where `id` = ? limit 1
👉 Solution: Eager load relationships (e.g. TestAttempt::with('questions')) in your controller.
```

### How It Works
Queries are parameterized into generic templates. If any template repeats more than 5 times during one request, it triggers an alert and generates copy-pasteable controller remedies.

---

## 6. Config & Env Drift Detector 🔄

### The Problem
A common local development mystery: *"It worked yesterday but not today!"* Developers silently edit `.env` variables or keys, break database configurations, and forget about the shift.

### The Solution
A configuration signature diff engine checking environment parameters between requests.

### What We Log
Drift alerts displaying changed environment keys and their old vs new values:
```text
⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!
  - SESSION_DRIVER changed from 'file' to 'redis'
```

### How It Works
Generates a SHA-256 hash checksum of monitored environment keys at request start. If it mismatches the stored cache signature from the previous request, it parses the differences key-by-key and warns the developer.

---

## 7. Compiled Blade Exception Resolver 🖌g

### The Problem
When a typo is written in a Blade template, Laravel throws a `ViewException` pointing to a temporary compiled cached PHP file (e.g. `/storage/framework/views/2fa8d7...php`), making the actual error extremely hard to find.

### The Solution
A compiled-to-source map resolver that instantly maps compiled view caches back to raw physical template files.

### What We Log
The physical raw `.blade.php` file path, the exact line, and line contents:
```text
File: /resources/views/components/book-card.blade.php
Line: 12
Original Line: <p>{{ $book->author->getShortName() }}</p>
```

### How It Works
Intercepts `ViewException` events, inspects Laravel's template compilation maps to resolve the cached hash back to its raw template registry, reads the file, and prints the exact error source.

---

## 8. Gate & Policy Authorization Profiler 🛡️

### The Problem
Encountering silent `403 Forbidden` screens is extremely common, yet identifying which specific policy or parameter rejected the user is an annoying guessing game.

### The Solution
Inline authorization policy checks auditor.

### What We Log
Permissions evaluated, arguments, and outcomes:
```text
* [ALLOWED] view-test (Arguments: App\Models\Test #4)
* [DENIED] update-test (Arguments: App\Models\Test #4)
```

### How It Works
Subscribes to gate callback evaluations using `Gate::after()` to compile ability parameters and result booleans.

---

## 9. Custom Session State Logger 💾

### The Problem
Session-backed data (wizard states, shopping carts) are hidden from default logs, requiring tedious debugging prints.

### The Solution
A session tracker filtering out framework keys to expose developer state parameters.

### What We Log
Active developer session keys:
```text
* test_attempt_id => 82
* active_step => 5
```

### How It Works
Reads `Session::all()`, excludes standard authentication and flash configurations, and outputs user-defined parameters.

---

## 10. Rich Actor Identification 👤

### The Problem
Standard logs print `Auth User ID: 14`, forcing developers to query database records to identify the developer or user role currently executing actions.

### The Solution
An actor enrichment card mapping User IDs to configurations (e.g., email or username).

### What We Log
Enriched profile string:
```text
Auth: User #14 (student: john@example.com)
```

### How It Works
Reads `'auth_identifiers'` keys array, resolves the active User model model properties, and formats the output.

---

## 11. Discord & Slack Crash Notification Channels 🔔

### The Problem
When running background webhooks or development staging testing workflows, HTTP 500 error pages and stack traces are silently swallowed or hidden, keeping bugs hidden until they disrupt test operations.

### The Solution
A low-latency, zero-dependency background HTTP dispatcher that sends styled JSON embed cards to Discord or Slack channels immediately when application crashes (HTTP Status `500` and above) occur.

### What We Log
This is a visual notification channel. No file logs are written directly by this channel, but it extracts active exceptions and request parameters:
```json
{
  "title": "🔥 Unhandled Application Crash Intercepted!",
  "description": "Class: RuntimeException\nMessage: Database Connection Dropped\nLocation: OrderController.php:L54",
  "fields": [
    { "name": "Method & URL", "value": "`POST` https://example.com/checkout" }
  ]
}
```

### How It Works
If the response status exceeds `500`, the middleware builds a rich embed payload and executes a silent background request using PHP's native `file_get_contents` streams with a small `2.0` second timeout, ensuring absolute zero latency for developers.

---

## 12. Execution Milestones & Performance Spans ⚡

### The Problem
While logging query execution and memory timing is helpful, profiling custom business actions, remote API integrations, or specific method blocks requires complex overhead tools.

### The Solution
A global, lightweight procedural helper `debug_span()` to wrap, record, and log timing milestones during the request lifecycle.

### What We Log
Spans named by the developer alongside timing metrics:
```text
- PERFORMANCE SPANS:
  * Stripe Checkout API Call: 5.40ms
  * PDF Bill Generator: 12.10ms
```

### How It Works
The helper `debug_span($name, $callback)` records the current time, runs the closure, calculates the difference, and appends the result to our central state singleton registry (`DebugLoggerManager`), compiling them into final logs.

---

## SPA, Inertia.js, & REST API Compatibility 🚀

### The Problem
Popular packages like Laravel Debugbar or custom visual injectors append massive inline `<script>` tags or HTML panels to the bottom of HTTP responses. While this works for traditional multi-page apps (MPAs), it completely breaks:
*   **Inertia.js AJAX Page Swaps**: Disrupts JSON hydration and throws console parsing exceptions.
*   **REST APIs / Mobile Client endpoints**: Appends HTML junk to JSON arrays, corrupting structural clients.
*   **Livewire & Alpine.js**: Interferes with DOM diffing algorithms, triggering state corruption.

### The Solution
**Laravel Agent-Debugger** solves this by keeping a **Zero-JS footprint** on AJAX/API requests:
1.  **Response Filter**: The floating glassmorphic Viewport Status Badge is **only** injected when the request is a standard, non-AJAX `text/html` document.
2.  **AJAX & API Isolation**: If a request is an Inertia.js page swap (`X-Inertia` header present) or returns `application/json`, the badge injection is cleanly bypassed.
3.  **High-Fidelity Offline Logs**: All profile events (database transactions, caught exceptions, execution milestones, breadcrumbs) are still recorded silently in `agent_debug.log` and the local dashboard.

This guarantees that your Inertia.js router remains 100% stable while you enjoy complete diagnostic visibility!
