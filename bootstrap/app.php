<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureSessionVersionMatches;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS terminates at Cloudflare's edge, then again at the nginx
        // sidecar's own listener -- php-fpm itself only ever sees plain
        // HTTP from nginx over the private Docker network (see
        // docker/nginx/default.conf's fastcgi_pass). Without trusting
        // that hop, Request::isSecure()/getHost()/ip() all reflect the
        // internal plain-HTTP connection instead of the client's real
        // HTTPS request, which is why a hard refresh could silently
        // lose the session: nginx isn't reachable from anywhere but
        // this app's own containers, so trusting it unconditionally
        // here is safe.
        $middleware->trustHosts(
            at: fn (): array => array_map(
                static fn (string $host): string => '^'.preg_quote($host, '/').'$',
                config('security.trusted_hosts'),
            ),
            subdomains: false,
        );
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', '*'));
        $middleware->trustProxies(at: $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));

        $middleware->web(append: [
            AssignRequestId::class,
            AddSecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureSessionVersionMatches::class,
        ]);

        // The Facebook Messenger webhook is Meta's server calling us
        // directly -- it authenticates via HMAC signature verification
        // (see FacebookWebhookController::receive()), not a CSRF token
        // it has no way to obtain. Matches Flask's one CSRF exemption
        // in this app.
        $middleware->validateCsrfTokens(except: [
            'webhooks/facebook/messenger',
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function ($response) {
            if (app()->bound('request') && ($requestId = request()->attributes->get('request_id'))) {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
