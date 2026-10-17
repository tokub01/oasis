<?php

/*
 * Proxies whose X-Forwarded-* headers Laravel trusts (read by Illuminate\Http\Middleware\TrustProxies).
 * Needed when Oasis runs behind a TLS-terminating proxy or tunnel, so URLs are generated with https.
 * null = trust no proxy (default). Comma-separated IPs/ranges, or * for any (only for local tunnels).
 */
return [
    'proxies' => env('OASIS_TRUSTED_PROXIES'),
];
