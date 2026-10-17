<?php

namespace App\Models\Concerns;

use App\Models\Friendship;
use App\Models\User;

/**
 * Friendships of an account (docs/oasis-network.md, "Freundschaften"), kept out of User.php. Friend ids are cached
 * per model instance, because privacy checks ask for them many times per request.
 */
trait HasFriends
{
    /** @var array<int>|null */
    protected ?array $friendIdsCache = null;

    /**
     * Ids of accepted friends.
     *
     * @return array<int>
     */
    public function friendIds(): array
    {
        return $this->friendIdsCache ??= Friendship::query()->whereNotNull('accepted_at')
            ->where(fn ($pair) => $pair->where('requester_id', $this->id)->orWhere('addressee_id', $this->id))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $friendship) => $friendship->requester_id === $this->id ? $friendship->addressee_id : $friendship->requester_id)
            ->map(fn ($id): int => (int) $id)->values()->all();
    }

    public function isFriendWith(User $other): bool
    {
        return in_array($other->id, $this->friendIds(), true);
    }

    /** The request or friendship with $other, in either direction, or null. */
    public function friendshipWith(User $other): ?Friendship
    {
        return Friendship::between($this, $other)->first();
    }

    /** Forget cached friend ids after a change in this request. */
    public function forgetFriends(): void
    {
        $this->friendIdsCache = null;
    }
}
