<?php

namespace App\Livewire\Network;

use App\Models\Oasis;
use App\Queries\Network\PostFeed;
use Illuminate\Contracts\View\View;

/** Member discovery feed at /app/entdecken, filtered through the shared visibility and safety query. */
class Discover extends NetworkPage
{
    /** Discover stays verified-member-only, showing public content through the existing visibility query. */
    public function render(): View
    {
        $user = $this->actor();

        return $this->page('livewire.network.discover', [
            'posts' => app(PostFeed::class)->discover($user),
            'oases' => Oasis::withCount('members')->orderByDesc('members_count')->limit(12)->get(),
        ], 'Entdecken', 'entdecken');
    }
}
