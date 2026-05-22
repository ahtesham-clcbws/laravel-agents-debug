---
layout: home

hero:
  name: "🔴 Laravel Agent-Debugger"
  text: "v3.1.1 — Zero-JS, High-Fidelity Diagnostics"
  tagline: A premium server-driven diagnostics, profiling, and visual dashboard suite for Laravel 12.x / 13.x — 13 interactive tabs, 29 server-side diagnostic profilers, real-time SSE streaming, and a fully interactive SPA dashboard.
  image:
    src: https://img.shields.io/badge/Laravel%20Agent--Debugger-v3.1.1-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
    alt: Laravel Agent-Debugger
  actions:
    - theme: brand
      text: Get Started →
      link: /vision
    - theme: alt
      text: All Features
      link: /features
    - theme: alt
      text: Configuration
      link: /configuration
    - theme: alt
      text: Artisan CLI
      link: /artisan

features:
  - icon: 🔴
    title: Viewport Status Badge
    details: Glassmorphic floating badge showing live execution time, memory usage, and query count on every HTML page.
  - icon: ⏱️
    title: Timeline Waterfall
    details: DevTools-style horizontal Gantt chart visualising Boot → DB Queries → Custom Spans → External HTTP calls.
  - icon: 🗺️
    title: Blade Composition Tree
    details: Visual flowchart of nested layout hierarchies, components, and partial views rendered per request.
  - icon: 📊
    title: Memory Flame-Graph
    details: Real PHP peak-heap allocation segmented into Boot, Eloquent, HTTP/Payload, and GC layers — live per request.
  - icon: 🔬
    title: SQL EXPLAIN Analyzer
    details: One-click query plan visualization highlighting full table scans, missing indexes, and join strategies.
  - icon: 🎭
    title: Outgoing API Mocks
    details: UI rule builder that intercepts Guzzle/Http::fake() calls without touching any application code.
  - icon: 🚿
    title: Real-time SSE Streaming
    details: Persistent Server-Sent Events stream delivering zero-lag log updates with AJAX polling fallback.
  - icon: 🧪
    title: PHPUnit Test Runner
    details: Dark terminal console inside the dashboard that executes the feature test suite and streams results live.
  - icon: 📧
    title: Outgoing Mail Sandbox
    details: Intercepts Laravel Mailables and renders exact HTML/Markdown email previews inside a dashboard tab.
  - icon: ⚠️
    title: N+1 Query Detector
    details: Parameterizes and counts query templates — alerts when any pattern runs 5+ times with eager-load fixes.
  - icon: 🩹
    title: Composer CVE Auditor
    details: Scans composer.lock against PHP security advisories and flags vulnerable packages on the dashboard.
  - icon: 🛠️
    title: Artisan Quick-Console
    details: One-click dashboard buttons for cache:clear, route:clear, and debug:clean without leaving the browser.
---

## The Origin Story: Born of Necessity 💡

> *"I developed this package because in some projects, I had to spend so much time and effort just explaining the problem to my AI coding agents. I started by building a lightweight middleware helper, slowly expanding features as needed. Soon, I realized that other developers faced the exact same bottleneck. That's when I decided to share this with the community. This package is truly an invention born of real-world necessity."*
>
> — **Ahtesham**, Creator of Laravel Agent-Debugger

By providing structured, high-fidelity, and compact server-side logs, **Laravel Agent-Debugger** acts as a powerful bridge between human developers, local runtimes, and AI coding agents.

---

## Why Choose Laravel Agent-Debugger?

*   **Require-Dev Isolation** — never shipped to production
*   **Zero JS Footprint** — no frontend script conflicts with Inertia, Livewire, or REST APIs
*   **Real-time SSE Dashboard** — live streaming updates without polling overhead
*   **13+29 Premium Features** — 13 interactive dashboard tabs and 29 server-side diagnostic profilers
*   **Artisan CLI Suite** — full lifecycle control from the terminal

### Quick Start

```bash
composer require --dev clcbws/laravel-agents-debug
php artisan vendor:publish --provider="LaravelAgentDebugger\DebugActivityServiceProvider"
php artisan agent:debug-on
```

**Packagist**: https://packagist.org/packages/clcbws/laravel-agents-debug
