<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers, applied to every request. Kept
 * deliberately permissive where this app's own architecture genuinely
 * needs it, rather than a maximally-strict policy that would break real
 * functionality:
 *
 *  - Every script/style/font/image this app ever loads is self-hosted
 *    (self-hosted @fontsource fonts, Vite-built same-origin assets, inline
 *    Lucide SVGs — see CLAUDE.md's font-hosting decision) — nothing here
 *    ever needs to reach an external CDN, so default-src 'self' costs
 *    nothing.
 *  - PayPal checkout is a genuine server-side redirect
 *    (redirect()->away($approveUrl) in the billing page) to PayPal's own
 *    hosted checkout page, not an embedded PayPal JS SDK/button/iframe —
 *    so no paypal.com CSP exception is needed anywhere for it to keep
 *    working.
 *  - script-src/style-src need 'unsafe-inline' (several Blade views use
 *    inline style="width:...%" bars, and Livewire/Alpine bootstrap via
 *    inline <script> tags) and script-src also needs 'unsafe-eval'
 *    specifically because Alpine.js (bundled with Livewire — this project
 *    deliberately doesn't add a separate Alpine package/CSP-build per
 *    CLAUDE.md) evaluates x-data/x-on expressions via `new Function(...)`.
 *    This is a real, intentional trade-off: it doesn't defend against an
 *    already-injected inline script, but it still blocks the exfiltration/
 *    "load a script from an attacker's own domain" step that also matters
 *    on top of the app's own output-escaping (see StoredXssSweepTest).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        // Only ever sent over an already-HTTPS response — sending it over
        // plain HTTP would be a no-op per spec anyway, but this also avoids
        // ever pinning a browser to HTTPS-only for a fresh shared-hosting
        // install that hasn't had an SSL certificate configured yet.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
