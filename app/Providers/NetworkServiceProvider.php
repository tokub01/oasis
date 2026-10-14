<?php

namespace App\Providers;

use App\Actions\Network\ManageInvitations;
use App\Http\Controllers\Network\InvitationController;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Stage 2 core network: posting and search budgets, accepting an invitation link right after registration. */
class NetworkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Generous for people, tight enough to stop scripted flooding.
        RateLimiter::for('network-posts', fn (Request $request) => Limit::perMinute(10)->by('network-posts:'.$request->user()?->id));
        // Search runs LIKE/REGEXP over Tropfen: bounded per account (guests per IP) so it cannot be used to load the DB.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by('search:'.($request->user()?->id ?? $request->ip())));

        // InvitationController::show remembers the token in the session; the new account accepts it on sign-up.
        Event::listen(Registered::class, function (Registered $event): void {
            $token = session()->pull(InvitationController::SESSION_KEY);
            if (is_string($token)) {
                app(ManageInvitations::class)->accept($token, $event->user);
            }
        });
    }
}
