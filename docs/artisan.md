# Artisan CLI Control Panel

The package registers a full console command control suite for managing profiler state, log lifecycle, and debug sessions directly from the terminal.

---

## 1. Enable Debugger

```bash
php artisan agent:debug-on
```

- Writes `AGENT_DEBUGGER_ENABLED=true` to `.env`
- Clears the application configuration cache automatically
- Activates all middleware profilers, SSE stream, and dashboard routes

---

## 2. Disable Debugger

```bash
php artisan agent:debug-off
```

- Writes `AGENT_DEBUGGER_ENABLED=false` to `.env`
- Silences all middleware intercept calculations immediately with zero overhead

---

## 3. Display Debug Status

```bash
php artisan agent:debug-status
```

Renders a formatted CLI table showing:
- Active State (Enabled vs Disabled)
- Logging Style (Single vs Date-wise)
- Storage Directory Location
- Monitored Environment Variables List
- Current File Storage Footprint (KB/MB)

---

## 4. Purge Diagnostic Logs

```bash
php artisan agent:debug-clean {--days=X}
```

- Purges all `.log` files in the storage directory
- `--days=X`: Retains files newer than X days, purging only aged logs
- Perfect for clearing context before new feature testing workflows

---

## 5. Live Terminal Stream Viewer

```bash
php artisan agent:debug-tail {--file=name.log}
```

Establishes a live tailing loop in the CLI terminal, rendering incoming requests as styled Termwind cards with:
- Color status badges (green 2xx, amber 3xx/4xx, red 5xx)
- N+1 warning boxes
- Exception trace blocks
- `--file=name.log`: Tail a specific named log file

---

## 6. Portable Session Recorder

```bash
php artisan agent:debug-record {--format=md} {--output=path}
```

Exports subsequent captured requests into a standalone, shareable debug bundle:
- `--format=md`: Markdown report (default)
- `--format=json`: Structured JSON package
- `--output=path`: Custom output path

Ideal for attaching to GitHub PRs, Jira issues, or AI agent prompts for senior review.
