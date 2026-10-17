<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageFollows;
use App\Actions\Network\ManageFriendship;
use App\Actions\Safety\ManageMute;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Support\Chat\ChatReach;
use App\Support\DemoProfiles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Public-readable member profile with safety-aware follows and feature-flagged demo fallback. */
class ProfileShow extends NetworkPage
{
    #[Locked]
    public string $handle;

    /** Complete the authorized action, then leave a page whose target is no longer readable. */
    public function blockUser(int $userId): void
    {
        parent::blockUser($userId);
        $this->redirectRoute('app.home', navigate: true);
    }

    /** Bind the existing handle URI; it cannot be replaced by a browser-side update. */
    public function mount(string $handle): void
    {
        $this->handle = mb_strtolower($handle);
    }

    /** Resolve the current profile and recheck visibility and bidirectional blocks. */
    private function target(): ?User
    {
        $user = User::where('username', $this->handle)->whereNotNull('email_verified_at')->with(['profile', 'privacySetting'])->first();
        if ($user) {
            abort_if(auth()->user()?->isBlockedWith($user) ?? false, 404);
            abort_unless($user->profileOrDefault()->isVisibleTo(auth()->user()), 404);
        }

        return $user;
    }

    /** Follow only a verified visible profile while entitled to participate. */
    public function follow(): void
    {
        $actor = $this->actor();
        Gate::authorize('participate');
        $target = $this->target();
        abort_unless($target !== null, 404);
        app(ManageFollows::class)->follow($actor, $target);
        $this->postStatus = 'Du folgst jetzt '.$target->display_name.'.';
    }

    /** Remove only the current account's follow, including in read-only mode. */
    public function unfollow(): void
    {
        $actor = $this->actor();
        $target = $this->target();
        abort_unless($target !== null, 404);
        app(ManageFollows::class)->unfollow($actor, $target);
        $this->postStatus = 'Du folgst '.$target->display_name.' nicht mehr.';
    }

    /** Ask for a friendship (or accept the other person's request); see ManageFriendship. */
    public function requestFriend(ManageFriendship $friendships): void
    {
        $target = $this->target();
        abort_unless($target !== null, 404);
        $friendship = $friendships->request($this->actor(), $target);
        $this->postStatus = $friendship->accepted_at !== null ? 'Ihr seid jetzt befreundet.' : 'Freundschaftsanfrage gesendet.';
    }

    public function acceptFriend(ManageFriendship $friendships): void
    {
        $target = $this->target();
        abort_unless($target !== null, 404);
        $friendships->accept($this->actor(), $target);
        $this->postStatus = 'Ihr seid jetzt befreundet.';
    }

    /** Withdraw, decline or end – silently. */
    public function removeFriend(ManageFriendship $friendships): void
    {
        $target = $this->target();
        abort_unless($target !== null, 404);
        $friendships->remove($this->actor(), $target);
        $this->postStatus = 'Erledigt.';
    }

    /** Undo only the current account's mute for this visible profile. */
    public function unmute(): void
    {
        $actor = $this->actor();
        $target = $this->target();
        abort_unless($target !== null, 404);
        app(ManageMute::class)->unmute($actor, $target);
        $this->postStatus = 'Stummschaltung aufgehoben.';
    }

    /** Real profiles use the same visibility query as before; demo profiles remain available behind their flag. */
    public function render(): View
    {
        $member = $this->target();
        if (! $member) {
            abort_unless(config('oasis.demo_pages'), 404);
            $profile = DemoProfiles::find($this->handle);
            abort_unless($profile !== null, 404);

            return view('livewire.network.profile-demo', ['handle' => $this->handle, 'profile' => $profile])
                ->layout('layouts.oasis', ['title' => 'Profil', 'noindex' => true]);
        }

        return $this->page('livewire.network.profile-show', ['member' => $member, 'profile' => $member->profileOrDefault(),
            'posts' => app(PostFeed::class)->byAuthor($member, auth()->user()),
            'followers' => $member->privacy()->allows('follower_count_audience', auth()->user()) ? $member->followers()->count() : null,
            'showLocation' => $member->privacy()->allows('location_audience', auth()->user()),
            'oases' => $member->privacy()->allows('oases_audience', auth()->user())
                ? $member->oases()->orderBy('name')->get()->filter->isVisibleTo(auth()->user()) : null,
            'following' => auth()->user()?->follows($member) ?? false,
            'friendship' => auth()->user()?->friendshipWith($member),
            'acceptsFriendRequests' => auth()->check() && app(ManageFriendship::class)->acceptsRequestFrom($member, auth()->user()),
            'canWrite' => ChatReach::writableIds(auth()->user(), collect([$member])) !== []], $member->display_name.' (@'.$member->username.')', 'profil', true, noindex: ! $member->privacy()->indexable());
    }
}
