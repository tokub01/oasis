<?php

namespace App\Livewire\Network;

use App\Models\Oasis;
use Illuminate\Contracts\View\View;

/** Lists joined and discoverable oases at /app/oasen for the current verified account. */
class OasisIndex extends NetworkPage
{
    /** List only data the current verified member may see. */
    public function render(): View
    {
        $mine = $this->actor()->oases()->withCount('members')->orderBy('name')->get();

        return $this->page('livewire.network.oasis-index', ['mine' => $mine,
            'others' => Oasis::whereNotIn('id', $mine->pluck('id'))->withCount('members')->orderBy('name')->get()], 'Oasen', 'oasen');
    }
}
