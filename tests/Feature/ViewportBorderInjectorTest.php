<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Tests\Feature;

use LaravelAgentDebugger\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaravelAgentDebugger\Middleware\ViewportBorderInjector;

class ViewportBorderInjectorTest extends TestCase
{
    /** @test */
    public function test_it_injects_red_border_stylesheet_on_html_responses()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/test-page', 'GET');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response('<html><body><h1>Hello World</h1></body></html>');
            $resp->headers->set('Content-Type', 'text/html');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringContainsString('id="agent-debugger-badge"', $content);
        $this->assertStringContainsString('background: rgba(18, 18, 18, 0.85)', $content);
    }

    /** @test */
    public function test_it_ignores_json_or_non_html_responses()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/api/data', 'GET');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response(json_encode(['status' => 'ok']));
            $resp->headers->set('Content-Type', 'application/json');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('id="agent-debugger-badge"', $content);
    }

    /** @test */
    public function test_it_ignores_dashboard_routes()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/_agent_debug/dashboard', 'GET');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response('<html><body>Dashboard Content</body></html>');
            $resp->headers->set('Content-Type', 'text/html');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('id="agent-debugger-badge"', $content);
    }

    /** @test */
    public function test_it_ignores_ajax_requests()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/test-page', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response('<html><body>AJAX Content</body></html>');
            $resp->headers->set('Content-Type', 'text/html');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('id="agent-debugger-badge"', $content);
    }

    /** @test */
    public function test_it_ignores_inertia_requests()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/test-page', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response('<html><body>Inertia Content</body></html>');
            $resp->headers->set('Content-Type', 'text/html');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('id="agent-debugger-badge"', $content);
    }

    /** @test */
    public function test_it_ignores_livewire_requests()
    {
        config(['agent-debugger.show_frontend_indicator' => true]);

        $request = Request::create('/test-page', 'GET');
        $request->headers->set('X-Livewire', 'true');
        $middleware = new ViewportBorderInjector();

        $response = $middleware->handle($request, function () {
            $resp = new Response('<html><body>Livewire Content</body></html>');
            $resp->headers->set('Content-Type', 'text/html');
            return $resp;
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('id="agent-debugger-badge"', $content);
    }
}
