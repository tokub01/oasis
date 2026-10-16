<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageOasis;
use App\Models\Oasis;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Host-only confirmation for closing an oasis while preserving its authors’ Tropfen. */
class OasisClose extends NetworkPage
{
    #[Locked]
    public int $oasisId;

    public bool $confirm = false;

    /** Keep the target immutable; host authorization is repeated when rendering and closing. */
    public function mount(Oasis $oasis): void
    {
        $this->oasisId = $oasis->id;
    }

    /** Only current hosts may close the oasis after explicit confirmation. */
    public function close(): void
    {
        $this->actor();
        $oasis = Oasis::findOrFail($this->oasisId);
        Gate::authorize('host', $oasis);
        $this->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Bitte bestätige, dass du die Oase schließen möchtest.']);
        app(ManageOasis::class)->close($oasis);
        $this->redirectRoute('network.oases.index', navigate: true);
    }

    /** Only current hosts see the destructive confirmation page. */
    public function render(): View
    {
        $this->actor();
        $oasis = Oasis::withCount(['members', 'posts'])->findOrFail($this->oasisId);
        Gate::authorize('host', $oasis);

        return $this->page('livewire.network.oasis-close', ['oasis' => $oasis], $oasis->name.' schließen', 'oasen');
    }
}
