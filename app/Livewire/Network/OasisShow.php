<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageFollows;
use App\Enums\Privacy\Audience;
use App\Models\Oasis;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Support\Chat\ChatReach;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
        $members = $isMember ? $this->members($oasis)->with(['privacySetting', 'profile'])->orderBy('display_name')
            ->limit(self::MEMBER_LIST_LIMIT)->get() : collect();
        // Others who chose not to appear for this viewer (contact filter); counted only without a name filter.
        $hidden = $isMember && trim($this->memberQuery) === ''
            ? $this->members($oasis, false)->count() - $this->members($oasis)->count() : 0;

        return $this->page('livewire.network.oasis-show', ['oasis' => $oasis,
            'hosts' => $oasis->hosts()->orderBy('display_name')->get(),
            'posts' => app(PostFeed::class)->inOasis($oasis, auth()->user()),
            'isMember' => $isMember, 'isHost' => $oasis->isHost(auth()->user()),
            'members' => $members, 'writable' => ChatReach::writableIds(auth()->user(), $members), 'hiddenMembers' => $hidden,
            'ownListAudience' => $isMember ? auth()->user()->privacy()->oasis_list_audience : null,
        ], $oasis->name, 'oasen', true,
            // An oasis shows Tropfen of many people; some do not want search engines, so the page is never indexed.
            noindex: true);
    }

    /**
     * Other members of the oasis, without anybody blocked either way, filtered by name and – unless $contactFilter is
     * false (for counting) – by each member's setting "Wer sieht dich in Mitgliederlisten von Oasen?": "members"
     * shows them to every member of the oasis, "contacts" (the default) only to their friends and people they follow,
     * "nobody" to no one. Members who do not appear cannot be opened or written to from the list.
     *
     * @return Builder<User>
     */
    private function members(Oasis $oasis, bool $contactFilter = true): Builder
    {
        $viewer = auth()->user();
        $query = trim($this->memberQuery);

        // The plain Eloquent query of the relation (with its join); users.* keeps the pivot id from overwriting users.id.
        return $oasis->members()->getQuery()->select('users.*')->whereKeyNot($viewer->id)->whereNotIn('users.id', $viewer->blockedUserIds())
            ->when($query !== '', fn ($members) => $members->where(fn ($match) => $match
                ->where('display_name', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('username', 'like', '%'.addcslashes(mb_strtolower($query), '%_\\').'%')))
            ->when($contactFilter, fn ($members) => $members->where(fn ($visible) => $visible
                ->whereExists(fn ($setting) => $setting->from('privacy_settings')->whereColumn('privacy_settings.user_id', 'users.id')
                    ->where('oasis_list_audience', Audience::Members->value))
                ->orWhere(fn ($contacts) => $contacts
                    ->where(fn ($setting) => $setting
                        ->whereNotExists(fn ($row) => $row->from('privacy_settings')->whereColumn('privacy_settings.user_id', 'users.id'))
                        ->orWhereExists(fn ($row) => $row->from('privacy_settings')->whereColumn('privacy_settings.user_id', 'users.id')
                            ->where('oasis_list_audience', Audience::Contacts->value)))
                    ->where(fn ($known) => $known
                        ->whereExists(fn ($follow) => $follow->from('follows')->whereColumn('follows.follower_id', 'users.id')
                            ->where('follows.followable_type', $viewer->getMorphClass())->where('follows.followable_id', $viewer->id))
                        ->orWhereExists(fn ($friend) => $friend->from('friendships')->whereNotNull('friendships.accepted_at')
                            ->where(fn ($pair) => $pair
                                ->where(fn ($a) => $a->whereColumn('friendships.requester_id', 'users.id')->where('friendships.addressee_id', $viewer->id))
                                ->orWhere(fn ($b) => $b->where('friendships.requester_id', $viewer->id)->whereColumn('friendships.addressee_id', 'users.id'))))))));
    }
}
