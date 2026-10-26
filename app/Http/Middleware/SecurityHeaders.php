<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and attach industry-standard security headers (ISO/IEC 25010 Security Compliance).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking via external iframes while permitting same-origin frames
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Legacy browser XSS filter protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Protect cross-origin referrer leakage
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict unauthorized access to sensitive device sensors/hardware APIs
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
