<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/** Adds a registration-only limiter to Fortify, which has no registration limiter config key. */
class ThrottleRegistration
{
    /** Reuse Laravel's limiter implementation and standard rate-limit response headers. */
    public function __construct(private ThrottleRequests $throttle) {}

    /** Apply six requests per minute only to the registration POST, including invalid submissions. */
    public function handle(Request $request, Closure $next): Response
    {
        return $request->routeIs('register.store')
            ? $this->throttle->handle($request, $next, 'registration')
            : $next($request);
    }
}
