<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_html_responses_include_security_headers_and_matching_script_nonce(): void
    {
        $response = $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([^']+)'/", $policy);
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $matches);
        $response->assertSee('nonce="'.$matches[1].'"', false);
    }

    public function test_hsts_is_only_sent_for_https_requests(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_each_response_gets_a_new_server_generated_request_id(): void
    {
        $first = $this->get('/login')->headers->get('X-Request-ID');
        $second = $this->get('/login')->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_unhandled_errors_hide_internal_details_and_keep_the_request_id(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/test-internal-error', fn () => throw new \RuntimeException('database password secret'));

        $this->get('/test-internal-error')
            ->assertStatus(500)
            ->assertHeader('X-Request-ID')
            ->assertDontSee('database password secret');
    }

    public function test_sensitive_routes_use_named_rate_limits(): void
    {
        $this->assertContains('throttle:order-writes', Route::getRoutes()->getByName('purchase-orders.store')->gatherMiddleware());
        $this->assertContains('throttle:return-actions', Route::getRoutes()->getByName('purchase-orders.returns.store')->gatherMiddleware());
        $this->assertContains('throttle:message-writes', Route::getRoutes()->getByName('messages.widget.send')->gatherMiddleware());
        $this->assertContains('throttle:admin-sensitive', Route::getRoutes()->getByName('admin.users.erase-now')->gatherMiddleware());
        $this->assertContains('throttle:report-exports', Route::getRoutes()->getByName('reports.orders.export')->gatherMiddleware());

        $request = Request::create('/reports/orders/export');
        $request->setUserResolver(fn () => null);
        $limit = RateLimiter::limiter('report-exports')($request);
        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }
}
