<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\ChangeRole;
use App\Enums\Role;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Role management on /moderation/roles (route moderation.roles.index), admins only (gate manageRoles).
 *
 * Every account row has its own role select and save button. Admins cannot change their own role here, so nobody
 * locks themselves out; the operator command `oasis:role` remains for that. New staff members also need confirmed
 * two-factor authentication before the moderation area opens for them.
 */
class Roles extends Component
{
    use ModerationValidation;
    use WithPagination;

    /** @var array<int, string> chosen role value per account id, filled for the accounts on the current page */
    public array $roles = [];

    /** Confirmation of the last change, announced through role="status". */
    public ?string $status = null;

    /** Store the chosen role of one account through the authorised ChangeRole action. */
    public function save(int $userId, ChangeRole $action): void
    {
        Gate::authorize('manageRoles');
        $user = User::findOrFail($userId);
        abort_if($user->is(auth()->user()), 403);
        $this->validate(['roles.'.$userId => ['required', Rule::enum(Role::class)]]);

        $action->handle($user, Role::from($this->roles[$userId]), auth()->user());
        $this->status = 'Die Rolle von '.$user->display_name.' wurde geändert.';
    }

    /** Re-checked on every Livewire request, so a revoked admin role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('manageRoles');
        $users = User::orderBy('display_name')->paginate(25, ['id', 'display_name', 'email', 'role']);
        $this->rememberCurrentRoles($users);

        return view('livewire.moderation.roles', ['users' => $users, 'roleCases' => Role::cases()])
            ->layout('layouts.staff', ['title' => 'Rollen', 'active' => 'roles']);
    }

    /**
     * Preselect each listed account's stored role, keeping choices that are not saved yet.
     *
     * @param  LengthAwarePaginator<int, User>  $users
     */
    private function rememberCurrentRoles(LengthAwarePaginator $users): void
    {
        foreach ($users as $user) {
            $this->roles[$user->id] ??= $user->role->value;
        }
    }
}
