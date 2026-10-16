<?php

namespace App\Livewire\Safety;

use App\Actions\Safety\ManageBlock;
use App\Actions\Safety\ManageMute;
use App\Models\Block;
use App\Models\Mute;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Safety overview on /app/sicherheit (route safety.index): lists accounts blocked or muted by the signed-in member
 * and lets them lift blocks or mutes without a full page reload.
 *
 * Blocking and muting start on profiles and Tropfen cards. Here every action re-verifies the signed-in account and
 * resolves the person only through the caller's own block/mute row: any other id fails with 404 and reveals nothing.
 */
class SafetyOverview extends Component
{
    /** Confirmation of the last safety change, announced via role="status". */
    #[Locked]
    public string $status = '';

    /**
     * Lift an existing block placed by the current verified member.
     */
    public function unblock(int $userId, ManageBlock $manage): void
    {
        $viewer = $this->actor();
        $target = Block::where('user_id', $viewer->id)->where('blocked_user_id', $userId)->firstOrFail()->blocked;
        $manage->unblock($viewer, $target);
        $this->status = 'Blockade von '.$target->display_name.' aufgehoben.';
    }

    /**
     * Lift an existing mute placed by the current verified member.
     */
    public function unmute(int $userId, ManageMute $manage): void
    {
        $viewer = $this->actor();
        $target = Mute::where('user_id', $viewer->id)->where('muted_user_id', $userId)->firstOrFail()->muted;
        $manage->unmute($viewer, $target);
        $this->status = 'Stummschaltung von '.$target->display_name.' aufgehoben.';
    }

    /**
     * Render the member's current blocked and muted account lists.
     */
    public function render(): View
    {
        $viewer = $this->actor();

        $blockedUsers = User::query()
            ->whereIn('id', Block::query()->where('user_id', $viewer->id)->select('blocked_user_id'))
            ->with('profile')
            ->orderBy('display_name')
            ->get();

        $mutedUsers = User::query()
            ->whereIn('id', Mute::query()->where('user_id', $viewer->id)->select('muted_user_id'))
            ->with('profile')
            ->orderBy('display_name')
            ->get();

        return view('livewire.safety.safety-overview', [
            'blockedUsers' => $blockedUsers,
            'mutedUsers' => $mutedUsers,
        ])->layout('layouts.app', ['title' => 'Sicherheit', 'active' => 'sicherheit', 'wide' => true]);
    }

    /**
     * Re-authorize the signed-in, verified member on every request.
     */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
