<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageOasis;
use App\Enums\Network\OasisRole;
use App\Models\Oasis;
use App\Models\User;
use App\Support\Network\OasisInput;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/** Creates oases and edits host-owned settings and roles, rechecking host rights before every change. */
class OasisForm extends NetworkPage
{
    #[Locked]
    public ?int $oasisId = null;

    public string $name = '';

    public string $description = '';

    public string $house_rules = '';

    public string $visibility = 'public';

    public string $color = 'spring';

    /** Authorize the create/edit route and copy only editable fields into the browser snapshot. */
    public function mount(?Oasis $oasis = null): void
    {
        $this->actor();
        if ($oasis?->exists) {
            $this->oasisId = $oasis->id;
            Gate::authorize('host', $oasis);
            foreach (['name', 'description', 'house_rules', 'color'] as $field) {
                $this->$field = $oasis->$field;
            }
            $this->visibility = $oasis->visibility->value;
        } else {
            Gate::authorize('create', Oasis::class);
        }
    }

    /** Validate all editable fields and re-authorize before calling the existing oasis action. */
    public function save(): void
    {
        $user = $this->actor();
        $oasis = $this->oasisId ? Oasis::findOrFail($this->oasisId) : null;
        Gate::authorize($oasis ? 'host' : 'create', $oasis ?? Oasis::class);
        $input = $this->validate(OasisInput::rules(), OasisInput::messages());
        $action = app(ManageOasis::class);
        $oasis = $oasis ? $action->update($oasis, $input) : $action->create($user, $input);
        $this->redirectRoute('network.oases.show', $oasis, navigate: true);
    }

    /** Only a current host can change an existing member's validated role. */
    public function setRole(int $userId, string $role): void
    {
        $this->actor();
        $oasis = Oasis::findOrFail($this->oasisId);
        Gate::authorize('host', $oasis);
        Validator::make(['role' => $role], ['role' => ['required', Rule::enum(OasisRole::class)]])->validate();
        app(ManageOasis::class)->setRole($oasis, User::findOrFail($userId), OasisRole::from($role));
        $this->postStatus = 'Die Rolle ist geändert.';
        if ($userId === auth()->id() && $role === 'member') {
            $this->redirectRoute('network.oases.show', $oasis, navigate: true);
        }
    }

    /** Permission changes during an edit take effect immediately; foreign member emails are never state. */
    public function render(): View
    {
        $this->actor();
        $oasis = $this->oasisId ? Oasis::findOrFail($this->oasisId) : new Oasis(['visibility' => 'public', 'color' => 'spring']);
        Gate::authorize($oasis->exists ? 'host' : 'create', $oasis->exists ? $oasis : Oasis::class);

        return $this->page('livewire.network.oasis-form', ['oasis' => $oasis,
            'members' => $oasis->exists ? $oasis->members()->orderBy('display_name')->get() : collect()], 'Oase', 'oasen');
    }
}
