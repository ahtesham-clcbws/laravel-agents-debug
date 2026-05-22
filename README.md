# Laravel Agent-Debugger

A Zero‑JS, high‑fidelity server‑side diagnostics & profiling suite for Laravel 12.x/13.x.

## Documentation

- **Vision** – https://ahtesham-clcbws.github.io/laravel-agents-debug/vision
- **Core Features** – https://ahtesham-clcbws.github.io/laravel-agents-debug/features
- **Configuration & Deployment** – https://ahtesham-clcbws.github.io/laravel-agents-debug/configuration
- **Artisan CLI Guide** – https://ahtesham-clcbws.github.io/laravel-agents-debug/artisan
- **Log Schema** – https://ahtesham-clcbws.github.io/laravel-agents-debug/schema
- **Changelog v2.8.0** – https://ahtesham-clcbws.github.io/laravel-agents-debug/changelogs/v2.8.0
- **Changelog v2.7.0** – https://ahtesham-clcbws.github.io/laravel-agents-debug/changelogs/v2.7.0

## Origin Story
> *"I developed this package because in some projects I had to spend a lot of time just explaining the problem to my AI coding agents. I started with a lightweight middleware helper and kept adding features. Soon I realized other developers faced the same bottleneck, so I decided to share it with the community. This is an invention of need."* – **Ahtesham**

## Why Choose Laravel Agent-Debugger?
- **Require‑dev isolation** – never shipped to production.
- **Zero JS footprint** – all diagnostics run server‑side.
- **Balanced payload truncation** – shows schema samples while keeping logs compact.
- **Artisan control suite** – enable/disable, status, log rotation, live tail.

## Quick Start Installation
```bash
composer require --dev clcbws/laravel-agents-debug
```
Publish the configuration:
```bash
php artisan vendor:publish --provider="LaravelAgentDebugger\DebugActivityServiceProvider"
```
Enable live profiling:
```bash
php artisan agent:debug-on
```

---

*For a full walkthrough, visit the live documentation site:* https://ahtesham-clcbws.github.io/laravel-agents-debug/
