<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
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
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureSessionVersionMatches::class,
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
    })->create();
