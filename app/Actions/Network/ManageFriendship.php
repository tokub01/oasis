<?php

namespace App\Actions\Network;

use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Models\Friendship;
use App\Models\User;
use App\Support\Security\AccountLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Friend requests and friendships (docs/oasis-network.md, "Freundschaften"), independent of follows:
 * - request: only if the other person accepts requests (privacy `friend_requests`), nobody blocked anybody, and – for
 *   members under 18 – only from people they follow; a pending request the other way is accepted instead;
 * - accept / decline (decline deletes the request silently), withdraw an own request, end a friendship.
 * Blocking ends every request and friendship between two people (ManageBlock).
 */
class ManageFriendship
{
    public function __construct(private readonly SendHint $hints) {}

    public function request(User $requester, User $addressee): Friendship
    {
        if ($requester->is($addressee)) {
            throw ValidationException::withMessages(['friend' => 'Mit dir selbst kannst du nicht befreundet sein.']);
        }
        if ($requester->isBlockedWith($addressee) || ! $this->acceptsRequestFrom($addressee, $requester)) {
            throw ValidationException::withMessages(['friend' => 'Diese Person nimmt gerade keine Freundschaftsanfragen an.']);
        }
        $existing = Friendship::between($requester, $addressee)->first();
        if ($existing !== null) {
            // A request the other way round: asking back means yes.
            if ($existing->accepted_at === null && $existing->requester_id === $addressee->id) {
                return $this->accept($requester, $addressee);
            }

            return $existing;
        }
        AccountLimits::hit($requester, 'follows', 'friend');
        $friendship = Friendship::create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);
        $this->hints->handle($addressee, $requester, NoticeKind::FriendRequest, 'friend-request:'.$friendship->id);

        return $friendship;
    }

    public function accept(User $addressee, User $requester): Friendship
    {
        return DB::transaction(function () use ($addressee, $requester): Friendship {
            $request = Friendship::where('requester_id', $requester->id)->where('addressee_id', $addressee->id)
                ->whereNull('accepted_at')->lockForUpdate()->firstOrFail();
            $request->update(['accepted_at' => now()]);
            $addressee->forgetFriends();
            $this->hints->handle($requester, $addressee, NoticeKind::FriendAccepted, 'friend-accepted:'.$request->id);

            return $request;
        });
    }

    /** Decline an incoming request, withdraw an own one or end a friendship: the row is simply removed. */
    public function remove(User $member, User $other): void
    {
        Friendship::between($member, $other)->delete();
        $member->forgetFriends();
    }

    /** Whether $addressee accepts a request from $requester right now. */
    public function acceptsRequestFrom(User $addressee, User $requester): bool
    {
        if (! $addressee->privacy()->friend_requests) {
            return false;
        }

        return ! $addressee->isMinor() || in_array($requester->id, $addressee->followedUserIds(), true);
    }
}
