<?php

namespace App\Http\Controllers\Moderation;

use App\Actions\Moderation\ChangeRole;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\RoleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Admin-only account role management; no role field is accepted by public account forms. */
class RoleController extends Controller
{
    /** Paginate accounts and expose the three allowed roles. */
    public function index(): View
    {
        Gate::authorize('manageRoles');

        return view('moderation.roles.index', [
            'users' => User::orderBy('display_name')->paginate(25, ['id', 'display_name', 'email', 'role']),
            'roles' => Role::cases(),
        ]);
    }

    /** Apply a validated role through the authorized role action. */
    public function update(RoleRequest $request, User $user, ChangeRole $action): RedirectResponse
    {
        $action->handle($user, Role::from($request->validated('role')), $request->user());

        return to_route('moderation.roles.index')->with('status', 'Die Rolle wurde geändert.');
    }
}
