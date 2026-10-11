<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every web response (stage 7, docs/oasis-security.md).
 *
 * Content-Security-Policy: scripts only from our origin or with the per-request nonce that Vite and Livewire add to
 * their tags (Livewire runs its CSP-safe Alpine build, config livewire.csp_safe), no eval except WebAssembly
 * compilation for libsodium in the chat ('wasm-unsafe-eval'), no plugins, no framing, no foreign form targets except
 * Stripe's hosted checkout and portal, network connections only to our origin and Pusher. Inline style attributes
 * stay allowed (bar widths); styles cannot execute code.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $cluster = config('broadcasting.connections.pusher.options.cluster') ?: 'eu';
        $scripts = ["'self'", "'nonce-$nonce'", "'wasm-unsafe-eval'"];
        $connect = ["'self'", "wss://ws-$cluster.pusher.com", "https://sockjs-$cluster.pusher.com"];
        $styles = ["'self'", "'unsafe-inline'"];
        // Local development with `npm run dev`: allow the Vite dev server and its websocket.
        if (Vite::isRunningHot()) {
            $origin = rtrim((string) file_get_contents(Vite::hotFile()));
            $scripts[] = $origin;
            $styles[] = $origin;
            $connect[] = $origin;
            $connect[] = preg_replace('#^http#', 'ws', $origin);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "img-src 'self' data: blob:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self' https://checkout.stripe.com https://billing.stripe.com",
        ]);
    }
}
