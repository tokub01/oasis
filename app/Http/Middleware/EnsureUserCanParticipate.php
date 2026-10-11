<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user can actively participate (post, reply, react, chat, create oases).
 *
 * Accounts with an expired trial and no active membership or gift are in read-only mode:
 * they may read, export and delete their account, but cannot submit new content.
 */
class EnsureUserCanParticipate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canParticipate()) {
            if ($request->expectsJson() || $request->is('api/*', 'app/chat/api/*')) {
                abort(403, 'Deine Testphase ist abgelaufen. Bitte schließe eine Mitgliedschaft ab, um mitzumachen.');
            }

            return redirect()->route('app.membership')
                ->with('status', 'Deine Testphase ist abgelaufen. Bitte schließe eine Mitgliedschaft ab, um aktiv mitzumachen.');
        }

        return $next($request);
    }
}
