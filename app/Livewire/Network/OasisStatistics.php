<?php

namespace App\Livewire\Network;

use App\Models\Oasis;
use App\Queries\Network\OasisStats;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Host-only aggregate statistics for one oasis, without exposing individual member activity. */
class OasisStatistics extends NetworkPage
{
    #[Locked]
    public int $oasisId;

    /** Preserve the route's immutable oasis target. */
    public function mount(Oasis $oasis): void
    {
        $this->oasisId = $oasis->id;
    }

    /** Re-authorize hosts before every aggregate statistics query. */
    public function render(): View
    {
        $this->actor();
        $oasis = Oasis::findOrFail($this->oasisId);
        Gate::authorize('host', $oasis);
        $stats = app(OasisStats::class);

        return $this->page('livewire.network.oasis-statistics', ['oasis' => $oasis, 'totals' => $stats->totals($oasis), 'weekly' => $stats->weekly($oasis)], 'Statistik · '.$oasis->name, 'oasen');
    }
}
