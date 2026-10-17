<?php

namespace App\Livewire\Network;

use App\Models\Vote;
use App\Queries\Network\MemberStats;
use App\Queries\Network\PostFeed;
use Illuminate\Contracts\View\View;

/** Chronological /app feed of followed people and oases, with quiet personal statistics and an explicit end. */
class Source extends NetworkPage
{
    /** New members are led to the skippable introduction once; a confirmation from there is shown here. */
    public function mount(): void
    {
        if ($this->actor()->onboarded_at === null) {
            $this->redirectRoute('app.welcome', navigate: true);

            return;
        }
        $this->postStatus = (string) session('status', '');
    }

    /** The chronological source and private summaries belong exclusively to the current verified account. */
    public function render(): View
    {
        $user = $this->actor();

        return $this->page('livewire.network.source', [
            'posts' => app(PostFeed::class)->source($user),
            'myOases' => $user->oases()->withCount('members')->orderBy('name')->get(),
            'followsAnyone' => $user->following()->exists(),
            'week' => app(MemberStats::class)->lastDays($user, 7),
            'openVote' => Vote::open()->orderBy('ends_at')->first(),
        ], 'Quelle');
    }
}
