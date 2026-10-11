<?php

namespace App\Actions\Moderation;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Central role elevation used by the admin endpoint and the trusted operator CLI. */
class ChangeRole
{
    /** An actor requires admin authorization; a null actor denotes the operator console command. */
    public function handle(User $target, Role $role, ?User $actor = null): void
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('manageRoles');
        }
        $target->role = $role;
        $target->save();
    }
}
