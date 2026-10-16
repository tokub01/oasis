<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageInvitations;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Private invitation list and creation endpoint with the existing monthly account budget. */
class Invitations extends NetworkPage
{
    /** Create an own invitation only with participation; the domain action enforces the monthly budget. */
    public function create(): void
    {
        $user = $this->actor();
        Gate::authorize('participate');
        app(ManageInvitations::class)->create($user);
        $this->postStatus = 'Dein Einladungslink ist bereit.';
    }

    /** Invitation tokens stay in server-rendered own-account query data, not public component properties. */
    public function render(): View
    {
        $user = $this->actor();

        return $this->page('livewire.network.invitations', ['invitations' => $user->invitations()->latest()->limit(24)->get(),
            'remaining' => app(ManageInvitations::class)->remainingThisMonth($user)], 'Einladen', 'einladen');
    }
}
