<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Adds security headers to all responses to protect against common attacks.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy - only send origin for cross-origin requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy - restrict access to browser features
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Content Security Policy (CSP)
        if (!app()->environment('local')) {
            $csp = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://www.paypal.com https://www.sandbox.paypal.com",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https:",
                "font-src 'self' data:",
                "connect-src 'self' https://api-m.paypal.com https://api-m.sandbox.paypal.com",
                "frame-src https://www.paypal.com https://www.sandbox.paypal.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ];
            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        // HTTP Strict Transport Security (HSTS) - only in production with HTTPS
        if (!app()->environment('local') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
