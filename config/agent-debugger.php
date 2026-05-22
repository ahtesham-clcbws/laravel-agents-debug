<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Switch
    |--------------------------------------------------------------------------
    |
    | When enabled, the package global middleware and listeners profile
    | incoming HTTP requests, SQL queries, authorization policies, session state
    | changes, peak memory usage, outgoing API requests, and view layouts.
    |
    */
    'enabled' => env('AGENT_DEBUGGER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Zero-JS Active Viewport Indicator
    |--------------------------------------------------------------------------
    |
    | When true, the package appends a premium glassmorphic floating status badge
    | showing request timings and queries count to HTML page outputs. This provides
    | a gorgeous visual confirmation that the profiling is actively running.
    |
    */
    'show_frontend_indicator' => env('AGENT_DEBUGGER_INDICATOR', true),

    /*
    |--------------------------------------------------------------------------
    | Logging Layout Style
    |--------------------------------------------------------------------------
    |
    | 'single' logs all request segments to 'agent_debug.log'.
    | 'date-wise' compiles separate daily log files ('agent_debug-YYYY-MM-DD.log')
    | for chronological order tracing.
    |
    */
    'log_style' => env('AGENT_DEBUGGER_STYLE', 'date-wise'),

    /*
    |--------------------------------------------------------------------------
    | Logging Directory
    |--------------------------------------------------------------------------
    |
    | Destination folder position where compiled debugger files are outputted.
    |
    */
    'log_path' => storage_path('logs'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Purge Old Logs
    |--------------------------------------------------------------------------
    |
    | Automatically purges/truncates old log records when local servers start
    | via `php artisan serve` commands.
    |
    */
    'auto_clean_debug' => env('AGENT_DEBUGGER_AUTO_CLEAN', true),

    /*
    |--------------------------------------------------------------------------
    | Intelligent Recursive Auto-Redaction
    |--------------------------------------------------------------------------
    |
    | Scans payloads, headers, cookies, and values, auto-redacting values matching
    | security terms (password, token, secrets, credit cards, CVVs, SSNs).
    |
    */
    'recursive_redaction' => env('AGENT_DEBUGGER_REDACT', true),

    /*
    |--------------------------------------------------------------------------
    | Actor Identity Fields
    |--------------------------------------------------------------------------
    |
    | Columns resolved from the authenticated User model. Translates User ID
    | numbers into readable values (e.g. Student #14: john@example.com).
    |
    */
    'auth_identifiers' => [
        'email',
        'username',
        'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy & Authorization Gates Tracking
    |--------------------------------------------------------------------------
    |
    | Logs evaluated results of authorization policy and gate permissions checks.
    | Perfect to identify silent 403 Forbidden blockers.
    |
    */
    'track_gates' => true,

    /*
    |--------------------------------------------------------------------------
    | Active Developer Session Tracking
    |--------------------------------------------------------------------------
    |
    | Profiles active, developer-allocated session parameters while ignoring
    | standard Laravel core tokens, wizard steps, or authentication hashes.
    |
    */
    'track_session' => true,

    /*
    |--------------------------------------------------------------------------
    | N+1 Query Loop Detector
    |--------------------------------------------------------------------------
    |
    | Scans query hashes. If matching query models execute 5+ times during a
    | single request, it prints N+1 Warnings with eager loading controller instructions.
    |
    */
    'detect_n_plus_one' => true,

    /*
    |--------------------------------------------------------------------------
    | Monitored Environment Config Keys
    |--------------------------------------------------------------------------
    |
    | Tracks environmental parameters, logging detailed warnings if active drivers,
    | connections, or cache configurations drift between requests.
    |
    */
    'track_config_drift' => true,

    'monitored_env_keys' => [
        'DB_CONNECTION',
        'SESSION_DRIVER',
        'QUEUE_CONNECTION',
        'CACHE_STORE',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Execution Flags
    |--------------------------------------------------------------------------
    |
    | Millisecond execution threshold to flag database queries as slow.
    |
    */
    'slow_query_threshold' => 10.0,

    /*
    | If true, backtraces the query caller file and line number.
    |
    */
    'log_query_source' => env('AGENT_DEBUGGER_QUERY_SOURCE', true),

    /*
    |--------------------------------------------------------------------------
    | Discord/Slack Crash Notification Webhooks
    |--------------------------------------------------------------------------
    |
    | Dispatch elegant JSON payload cards to webhooks upon 500 status crashes.
    |
    */
    'webhook_url' => env('AGENT_DEBUGGER_WEBHOOK', null),

    /*
    |--------------------------------------------------------------------------
    | Balanced Payload Truncation Limits
    |--------------------------------------------------------------------------
    |
    | Item count threshold. Larger database collections or Inertia arrays retain
    | specified element samples to show schema context, truncating rest.
    |
    */
    'payload_sample_size' => 2,

    /*
    |--------------------------------------------------------------------------
    | Request URL Filtering & Exceptions
    |--------------------------------------------------------------------------
    |
    | Exclude specific pages or frameworks channels from logging.
    |
    */
    'except' => [
        '_debugbar/*',
        'horizon/*',
        'telescope/*',
        'sanctum/csrf-cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted Header Keys
    |--------------------------------------------------------------------------
    |
    | Exclude heavy or secure request headers from output files.
    |
    */
    'redact_headers' => [
        'cookie',
        'authorization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Breadcrumb Trail Limit
    |--------------------------------------------------------------------------
    |
    | Number of navigation paths saved in request trails history buffers.
    |
    */
    'breadcrumb_limit' => 10,

    /*
    |--------------------------------------------------------------------------
    | Livewire Polling Exclusions
    |--------------------------------------------------------------------------
    |
    | Livewire polling updates or updates to exclude to prevent log bloat.
    |
    */
    'ignore_livewire_actions' => [
        'polling',
        'heartbeat',
    ],
];
