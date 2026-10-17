<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageFollows;
use App\Models\Oasis;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Support\Chat\ChatReach;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Public-readable oasis page with a chronological feed and authorized membership actions. */
class OasisShow extends NetworkPage
{
    #[Locked]
    public int $oasisId;

    /** Name filter of the member list, which only members of the oasis see. */
    public string $memberQuery = '';

    /** How many members the list shows at once; the filter finds everyone else. */
    public const MEMBER_LIST_LIMIT = 12;

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
        $isMember = $oasis->isMember(auth()->user());
        $members = $isMember ? $this->members($oasis) : collect();

        return $this->page('livewire.network.oasis-show', ['oasis' => $oasis,
            'hosts' => $oasis->hosts()->orderBy('display_name')->get(),
            'posts' => app(PostFeed::class)->inOasis($oasis, auth()->user()),
            'isMember' => $isMember, 'isHost' => $oasis->isHost(auth()->user()),
            'members' => $members, 'writable' => ChatReach::writableIds(auth()->user(), $members),
        ], $oasis->name, 'oasen', true,
            // An oasis shows Tropfen of many people; some do not want search engines, so the page is never indexed.
            noindex: true);
    }

    /**
     * Other members of the oasis, without anybody blocked either way, filtered by name.
     *
     * @return Collection<int, User>
     */
    private function members(Oasis $oasis): Collection
    {
        $viewer = auth()->user();
        $query = trim($this->memberQuery);

        return $oasis->members()->whereKeyNot($viewer->id)->whereNotIn('users.id', $viewer->blockedUserIds())
            ->when($query !== '', fn ($members) => $members->where(fn ($match) => $match
                ->where('display_name', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('username', 'like', '%'.addcslashes(mb_strtolower($query), '%_\\').'%')))
            ->with(['privacySetting', 'profile'])->orderBy('display_name')->limit(self::MEMBER_LIST_LIMIT)->get();
    }
}
