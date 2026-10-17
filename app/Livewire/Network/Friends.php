<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageFriendship;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * /app/freunde (route network.friends): incoming friend requests (accept, decline), friends (end) and own open
 * requests (withdraw). Declining tells nobody anything. A switch-off link leads to the privacy setting
 * `friend_requests` (docs/oasis-network.md, "Freundschaften").
 */
class Friends extends NetworkPage
{
    public function accept(int $userId, ManageFriendship $friendships): void
    {
        $friendships->accept($this->actor(), User::findOrFail($userId));
        $this->postStatus = 'Ihr seid jetzt befreundet.';
    }

    /** Decline, withdraw or end – the row disappears silently. */
    public function remove(int $userId, ManageFriendship $friendships): void
    {
        $friendships->remove($this->actor(), User::findOrFail($userId));
        $this->postStatus = 'Erledigt.';
    }

    public function render(): View
    {
        $me = $this->actor();
        $rows = Friendship::with(['requester.profile', 'addressee.profile'])
            ->where(fn ($pair) => $pair->where('requester_id', $me->id)->orWhere('addressee_id', $me->id))
            ->latest('id')->get()
            ->reject(fn (Friendship $row) => $me->isBlockedWith($row->requester_id === $me->id ? $row->addressee : $row->requester));

        return $this->page('livewire.network.friends', [
            'incoming' => $rows->filter(fn (Friendship $row) => $row->accepted_at === null && $row->addressee_id === $me->id)->map->requester->values(),
            'outgoing' => $rows->filter(fn (Friendship $row) => $row->accepted_at === null && $row->requester_id === $me->id)->map->addressee->values(),
            'friends' => $rows->filter(fn (Friendship $row) => $row->accepted_at !== null)
                ->map(fn (Friendship $row) => $row->requester_id === $me->id ? $row->addressee : $row->requester)->sortBy('display_name')->values(),
            'acceptsRequests' => $me->privacy()->friend_requests,
        ], 'Freund:innen', 'freunde');
    }
}
