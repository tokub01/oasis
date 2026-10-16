<?php

namespace App\Livewire\Network;

use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/** Public invitation landing page; the secret URL token stays in the registration session. */
class InvitationLanding extends NetworkPage
{
    public const SESSION_KEY = 'oasis.invitation';

    #[Locked]
    public ?int $invitationId = null;

    /** Resolve the secret from the URL once and keep it only in the registration session. */
    public function mount(string $token): void
    {
        $invitation = Invitation::open()->where('token', $token)->first();
        $this->invitationId = $invitation?->id;
        if ($invitation && ! auth()->check()) {
            session()->put(self::SESSION_KEY, $token);
        }
    }

    /** Invalid and expired invitations have a neutral public response without disclosing the secret. */
    public function render(): View
    {
        return view('livewire.network.invitation-landing', ['invitation' => Invitation::open()->with('inviter')->find($this->invitationId)])
            ->layout('layouts.oasis', ['title' => 'Einladung', 'noindex' => true]);
    }
}
