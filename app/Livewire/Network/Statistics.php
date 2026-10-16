<?php

namespace App\Livewire\Network;

use App\Queries\Network\MemberStats;
use Illuminate\Contracts\View\View;

/** Private aggregate network statistics for the current verified account. */
class Statistics extends NetworkPage
{
    /** Statistics are always computed for the current verified account, never a client-supplied user ID. */
    public function render(): View
    {
        $user = $this->actor();
        $stats = app(MemberStats::class);

        return $this->page('livewire.network.statistics', ['totals' => $stats->totals($user),
            'weekly' => $stats->weekly($user), 'topPosts' => $stats->topPosts($user)], 'Deine Zahlen', 'zahlen');
    }
}
