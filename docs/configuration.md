# Configuration Schema

The package provides options to customize log styles, payload exclusions, rotation behaviors, performance limits, security redaction setups, and visual alerts.

---

## The Default Config File

Create and customize your configuration settings inside `config/agent-debugger.php`:

```php
return [
    // Global active switch (read from .env or toggled via Artisan commands)
    'enabled' => env('AGENT_DEBUGGER_ENABLED', false),

    // Injects a solid 2px red border framing the viewport to notify that logging is active
    'show_frontend_indicator' => env('AGENT_DEBUGGER_INDICATOR', true),

    // Log File Rotation Options: 'single' vs 'date-wise'
    'log_style' => env('AGENT_DEBUGGER_STYLE', 'date-wise'),

    // Log file destination path
    'log_path' => storage_path('logs'),

    // Auto-clean logs on application start
    'auto_clean_debug' => env('AGENT_DEBUGGER_AUTO_CLEAN', true),

    // Enable intelligent recursive auto-redaction
    'recursive_redaction' => env('AGENT_DEBUGGER_REDACT', true),

    // Fields to extract from the authenticated User model to identify actor
    'auth_identifiers' => [
        'email',
        'username',
        'name',
    ],

    // Track policy checks and authorization gates (Gate::allows, Gate::denies)
    'track_gates' => true,

    // Track active developer-defined session state values
    'track_session' => true,

    // Detect and flag repeating queries (N+1 queries loops)
    'detect_n_plus_one' => true,

    // Alert if key local environment configuration settings drift between requests
    'track_config_drift' => true,

    // Environmental keys to monitor for configuration drifts
    'monitored_env_keys' => [
        'DB_CONNECTION',
        'SESSION_DRIVER',
        'QUEUE_CONNECTION',
        'CACHE_STORE',
    ],

    // Threshold (in ms) to flag slow database queries (0 to disable flagging)
    'slow_query_threshold' => 10.0,

    // If true, backtraces query caller file and line number
    'log_query_source' => env('AGENT_DEBUGGER_QUERY_SOURCE', true),

    // Discord or Slack Webhook URL to dispatch crashes
    'webhook_url' => env('AGENT_DEBUGGER_WEBHOOK', null),

    // Inertia & API payload truncation: retains structural sample size
    'payload_sample_size' => 2,

    // Exclude certain URLs from being logged
    'except' => [
        '_debugbar/*',
        'horizon/*',
        'telescope/*',
        'sanctum/csrf-cookie',
    ],

    // Exclude custom request headers from logs (like long cookies)
    'redact_headers' => [
        'cookie',
        'authorization',
    ],

    // Limits number of session breadcrumbs to retain
    'breadcrumb_limit' => 10,

    // Exclude specific Livewire methods/updates from log noise
    'ignore_livewire_actions' => [
        'polling',
        'heartbeat',
    ],
];
```

---

## Essential Configuration Parameters Explained

### 1. log_style
*   **Values**: `'single'` or `'date-wise'`
*   **Description**: `'single'` writes everything into `agent_debug.log`, rotating it on max file limit parameters. `'date-wise'` creates distinct daily files (`agent_debug-YYYY-MM-DD.log`) for tracking chronological execution histories.

### 2. recursive_redaction
*   **Values**: `true` or `false`
*   **Description**: Recursively scans request variables and redacts security keys matching standard credential terms (password, secret, ssn, card, cvv).

### 3. auth_identifiers
*   **Values**: Array of model keys
*   **Description**: Defines user columns resolved from the active logged-in model. This lets the backend enrich the log header, converting User ID numbers into readable identifiers like names or email addresses.

### 4. payload_sample_size
*   **Values**: Integer (Default: 2)
*   **Description**: Specifies structural template item sample counts. When logging massive lists or Eloquent collections, the profiler prints the specified number of items to show typing schemas while truncating the rest, keeping token usage under control.

### 5. webhook_url
*   **Values**: Webhook string URL (Default: `null`)
*   **Description**: A Discord or Slack webhook channel URL. The debugger automatically catches unhandled application crashes (HTTP Status `500` and above) and posts styled, comprehensive details in the background without affecting request roundtrip times.

### 6. log_query_source
*   **Values**: `true` or `false` (Default: `true`)
*   **Description**: When active, the SQL database listener traces query executions back to the exact class and file line (e.g. `OrderRepository.php line 42`) where they were fired, writing it right alongside SQL statements.
