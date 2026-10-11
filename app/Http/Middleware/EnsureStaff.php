<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff access requires a staff role and a confirmed, configured Fortify second factor. */
class EnsureStaff
{
    /** Deny members; send staff without confirmed 2FA to account settings with German guidance. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->role->isStaff(), 403);
        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            return to_route('app.settings')->with('status', 'Bitte aktiviere und bestätige die Zwei-Faktor-Authentifizierung für die Moderation.');
        }

        return $next($request);
    }
}
