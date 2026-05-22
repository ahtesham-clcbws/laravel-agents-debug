# Artisan CLI Control Panel

The package registers dynamic console command control hooks so that human developers and AI coding agents can manage logger status and active directory sizes right from the terminal.

---

## 1. Enable Debugger

```bash
php artisan agent:debug-on
```

*   **Action**: Scans and updates your `.env` configuration file, adding or updating:
    `AGENT_DEBUGGER_ENABLED=true`
*   **Downstream Effects**: Automatically clears the application's configuration cache so that shifts are registered instantly across local processes.

---

## 2. Disable Debugger

```bash
php artisan agent:debug-off
```

*   **Action**: Scans and updates your `.env` configuration file, adding or updating:
    `AGENT_DEBUGGER_ENABLED=false`
*   **Downstream Effects**: Instantly isolates and silences the middleware from executing intercept calculations, reducing active profiling overhead.

---

## 3. Display Debug Status

```bash
php artisan agent:debug-status
```

*   **Action**: Renders a formatted CLI console table compiling active parameter configurations:
    *   Active State (Enabled vs Disabled)
    *   Logging Style (Single vs Date-wise)
    *   Logging Directory Location
    *   Monitored Environment Variables Config List
    *   Current File Storage Footprint sizes (in KB/MB)

---

## 4. Purge Diagnostics Logs

```bash
php artisan agent:debug-clean {--days=X}
```

*   **Action**: Purges, truncates, and completely cleans active `.log` files inside your storage directory.
*   **Parameters**:
    *   `--days=X`: Purges only daily log files and single log states older than `X` days, retaining recent profiles.
*   **Use Cases**: Perfect for clearing out old debug footprints before initiating a new feature testing workflow, keeping your context logs compact and targeted.

---

## 5. Live Stream Tail Viewer

```bash
php artisan agent:debug-tail {--file=name.log}
```

*   **Action**: Establishes a live background tailing loop in the CLI terminal rendering incoming HTTP requests, status cards, warning boxes (N+1 query warnings), and error traces as they happen.
*   **Parameters**:
    *   `--file=name.log`: Tail a specific log file instead of the default date-wise file.
*   **Aesthetics**: Beautifully styled using Laravel Termwind with color status badges, bold highlights, and clean typography.
