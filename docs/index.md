---
layout: home

hero:
  name: Laravel Agent-Debugger
  text: Zero-JS, High-Fidelity Diagnostics
  tagline: A premium server-driven diagnostics and profiling suite for Laravel 12.x/13.x
  actions:
    - theme: brand
      text: Get Started
      link: /vision
    - theme: alt
      text: Configuration
      link: /configuration
    - theme: alt
      text: View Log Schema
      link: /schema

features:
  - icon: 🔴
    title: Zero-JS Viewport Status Badge
    details: Injects a premium, glassmorphic floating badge showing real-time execution times and query counts in the bottom-right corner.
  - icon: 🧭
    title: Request Breadcrumbs
    details: Logs the last 10 visited URLs with methods and status codes to reconstruct user journeys leading to a crash.
  - icon: 💳
    title: DB Transaction Auditor
    details: Tracks begins, commits, and rollbacks directly inline with queries to debug silent webhook database rollbacks.
  - icon: ⚠️
    title: N+1 Query Loop Detector
    details: Scans query counts in real-time, alerts on redundant database calls, and suggests eager loading relationships.
  - icon: 🔄
    title: Config & Env Drift Alert
    details: Checks environment states between requests and alerts if driver configurations or secrets are modified.
  - icon: 🖌️
    title: Blade Compiler Resolver
    details: Maps unreadable compiled storage paths back to their original physical raw .blade.php file and error lines.
---

## The Origin Story: Born of Necessity 💡

> *"I developed this package because in some projects, I had to spend so much time and effort just explaining the problem to my AI coding agents. I started by building a lightweight middleware helper, slowly expanding features as needed. Soon, I realized that other developers faced the exact same bottleneck. That's when I decided to share this with the community. This package is truly an invention born of real-world necessity."*
>
> — **Ahtesham**, Creator of Laravel Agent-Debugger

By providing structured, high-fidelity, and compact server-side logs, **Laravel Agent-Debugger** acts as a powerful bridge between human developers, local runtimes, and AI coding agents. It compiles exactly the diagnostic context an AI needs to identify and resolve complex bugs in a fraction of a second—completely eliminating manual trace explanations.

---

## Why Choose Laravel Agent-Debugger?

Standard Laravel debugging tools are heavy, depend on frontend scripts (which fail in custom Inertia or REST API contexts), or leak memory on high-volume requests. **Laravel Agent-Debugger** solves this by operating entirely on the server-side as a global middleware inspector:

*   **Require-Dev Isolation**: Automatically excluded from production caches.
*   **Zero JS Footprint**: High-fidelity logs are delivered straight to your local `.log` storage.
*   **Balanced Payload Truncation**: Shows schemas while keeping sizes optimal for developer reading and AI parsing.
*   **Artisan Control Command Suite**: Command configurations, log rotations, and cleanups from the CLI.

### Quick Start Installation

```bash
composer require --dev clcbws/laravel-agents-debug
```

Publish package configurations:

```bash
php artisan vendor:publish --provider="LaravelAgentDebugger\DebugActivityServiceProvider"
```

Activate live profiling immediately:

```bash
php artisan agent:debug-on
```

**Packagist**: https://packagist.org/packages/clcbws/laravel-agents-debug
