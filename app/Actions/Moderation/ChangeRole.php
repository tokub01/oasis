<?php

namespace App\Actions\Moderation;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Central role elevation used by the admin endpoint and the trusted operator CLI. Admins manage members and
 * moderators; only the operator account (User::isSuperAdmin) makes someone an admin or changes an admin's role, and
 * nobody changes the operator account here.
 */
class ChangeRole
{
    /** An actor requires admin authorization; a null actor denotes the operator console command. */
    public function handle(User $target, Role $role, ?User $actor = null): void
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('manageRoles');
            abort_if($target->isSuperAdmin(), 403);
            abort_if(($role === Role::Admin || $target->role === Role::Admin) && ! $actor->isSuperAdmin(), 403);
        }
        $target->role = $role;
        $target->save();
    }
}
