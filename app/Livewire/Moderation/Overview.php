<?php

namespace App\Livewire\Moderation;

use App\Queries\Moderation\QueueOverview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Moderation start page on /moderation (route moderation.index): overview figures and the latest activity, with the
 * filterable Queue component below. Staff only (route middleware `staff`, checked again on every render).
 */
class Overview extends Component
{
    /** Queue figures and recent activity for staff; authorised again on every render. */
    public function render(QueueOverview $overview): View
    {
        Gate::authorize('viewModeration');

        return view('livewire.moderation.overview', [
            'figures' => $overview->figures(auth()->user()),
            'activity' => $overview->recentActivity(),
        ])->layout('layouts.staff', ['title' => 'Warteschlange', 'active' => 'queue']);
    }
}
