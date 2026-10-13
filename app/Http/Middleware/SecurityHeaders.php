<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'same-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('X-Robots-Tag', 'noindex, nofollow');
        if (! app()->environment('local', 'testing')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            // No third-party origins at all: fonts, scripts and images are self-hosted.
            // 'unsafe-eval' is required by Alpine.js expressions; inline <script> stays blocked.
            $h->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
        }
        if ($request->user()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
