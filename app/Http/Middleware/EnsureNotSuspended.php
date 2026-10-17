<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A locked account (docs/oasis-moderation.md, "Sperren") only reaches /app/gesperrt and what a member must always be
 * able to do: sign out, settings (password, account deletion), data export, membership and cancellation, complaints,
 * and the public pages. Everything else redirects to /app/gesperrt. Writing is additionally blocked by
 * User::canParticipate, so Livewire requests (allowed here for the pages above) cannot post either.
 */
class EnsureNotSuspended
{
    private const ALLOWED_ROUTES = [
        'app.suspended', 'logout', 'app.settings', 'account.export', 'app.membership', 'app.membership.*',
        'password.*', 'verification.*', 'user-password.update', 'moderation.complaint.*', 'oasis.*',
        'default-livewire.update', 'livewire.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $request->routeIs(...self::ALLOWED_ROUTES) || $request->is('livewire*') || ! $user->isSuspended()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            abort(403, 'Dein Konto ist gesperrt.');
        }

        return redirect()->route('app.suspended');
    }
}
