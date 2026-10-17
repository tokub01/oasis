<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\LiftSuspension;
use App\Models\AccountSuspension;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * /moderation/sperren (route moderation.suspensions): locked accounts with accusation, text, the member's statement and
 * strikes, plus the most recently ended locks. "Freigeben" ends a lock explicitly (LiftSuspension; a ban only by
 * admins). Deciding the linked case also ends or keeps the lock (ApplyAccountConsequences).
 */
class Suspensions extends Component
{
    public ?string $status = null;

    public function release(int $id, LiftSuspension $lift): void
    {
        Gate::authorize('viewModeration');
        $suspension = AccountSuspension::active()->findOrFail($id);
        $lift->handle($suspension, auth()->user(), 'released');
        $this->status = 'Das Konto von '.$suspension->user->display_name.' ist wieder freigegeben.';
    }

    public function render(): View
    {
        Gate::authorize('viewModeration');

        return view('livewire.moderation.suspensions', [
            'active' => AccountSuspension::active()->with(['user', 'moderationCase'])->oldest('started_at')->get(),
            'ended' => AccountSuspension::whereNotNull('ended_at')->with('user')->latest('ended_at')->limit(20)->get(),
            'limit' => (int) config('oasis.moderation.strikes_for_ban'),
        ])->layout('layouts.staff', ['title' => 'Sperren', 'active' => 'suspensions']);
    }
}
