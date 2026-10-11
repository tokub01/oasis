<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Stage 6 permissions: the admin area and voting rights. */
class GovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Admin area (statistics, transparency reports, votes): admins with confirmed 2FA, like role management.
        Gate::define('administrate', fn (User $user) => Gate::forUser($user)->allows('manageRoles'));

        // "Eine Stimme bei jeder Abstimmung": every member with an active paid or gifted membership, any tier.
        Gate::define('vote', fn (User $user) => $user->hasVerifiedEmail()
            && ($user->hasActiveMembership() || $user->hasActiveGiftMembership()));
    }
}
