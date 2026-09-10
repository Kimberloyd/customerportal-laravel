<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $host = $request->getHost();
        $policy = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self' ws://{$host} wss://{$host}",
            "font-src 'self' https://fonts.bunny.net data:",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: blob:",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
        ]);

        $response->headers->set(
            config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
            $policy,
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        if (config('security.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
