<?php

namespace App\Providers;

use App\Actions\Network\ManageInvitations;
use App\Livewire\Network\InvitationLanding;
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
        // Search runs LIKE/REGEXP over Tropfen: bounded per account (guests per IP) so it cannot be used to load the DB.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by('search:'.($request->user()?->id ?? $request->ip())));

        // InvitationLanding::mount remembers the token in the session; the new account accepts it on sign-up.
        Event::listen(Registered::class, function (Registered $event): void {
            $token = session()->pull(InvitationLanding::SESSION_KEY);
            if (is_string($token)) {
                app(ManageInvitations::class)->accept($token, $event->user);
            }
        });
    }
}
