<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageFollows;
use App\Models\Oasis;
use App\Queries\Network\PostFeed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Public-readable oasis page with a chronological feed and authorized membership actions. */
class OasisShow extends NetworkPage
{
    #[Locked]
    public int $oasisId;

    /** Resolve the slug using the existing route model binding. */
    public function mount(Oasis $oasis): void
    {
        $this->oasisId = $oasis->id;
    }

    /** Follow a currently visible oasis only with paid/trial participation. */
    public function follow(): void
    {
        $user = $this->actor();
        $oasis = Oasis::findOrFail($this->oasisId);
        Gate::authorize('participate');
        Gate::authorize('view', $oasis);
        app(ManageFollows::class)->follow($user, $oasis);
        $this->postStatus = 'Du folgst jetzt der Oase '.$oasis->name.'.';
    }

    /** Withdraw only the current account's membership; the action protects the last host. */
    public function unfollow(): void
    {
        $user = $this->actor();
        app(ManageFollows::class)->unfollow($user, Oasis::findOrFail($this->oasisId));
        $this->postStatus = 'Du folgst der Oase nicht mehr.';
    }

    /** Recheck visibility and reload host/member status on each response. */
    public function render(): View
    {
        $oasis = Oasis::withCount('members')->findOrFail($this->oasisId);
        abort_unless(Gate::allows('view', $oasis), 404);

        return $this->page('livewire.network.oasis-show', ['oasis' => $oasis,
            'hosts' => $oasis->hosts()->orderBy('display_name')->get(),
            'posts' => app(PostFeed::class)->inOasis($oasis, auth()->user()),
            'isMember' => $oasis->isMember(auth()->user()), 'isHost' => $oasis->isHost(auth()->user()),
        ], $oasis->name, 'oasen', true);
    }
}
