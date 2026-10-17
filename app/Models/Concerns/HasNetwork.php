<?php

namespace App\Models\Concerns;

use App\Models\Follow;
use App\Models\Invitation;
use App\Models\Oasis;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Stage 2 relations of an account (profile, follows, oases, invitations), kept out of User.php so account,
 * billing and network concerns stay separate. Used by {@see User}.
 */
trait HasNetwork
{
    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** The stored profile, or an unsaved default one (members-only, sun avatar) for accounts that never edited it. */
    public function profileOrDefault(): Profile
    {
        return $this->profile ?? new Profile(['user_id' => $this->id]);
    }

    /**
     * Follows this account made (of people and oases).
     *
     * @return HasMany<Follow, $this>
     */
    public function following(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Follows of this account: its "Karawane".
     *
     * @return MorphMany<Follow, $this>
     */
    public function followers(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    /** @return BelongsToMany<Oasis, $this> */
    public function oases(): BelongsToMany
    {
        return $this->belongsToMany(Oasis::class, 'oasis_members')->withPivot(['role', 'joined_at']);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'inviter_id');
    }

    /** Whether this account follows a person or an oasis. */
    public function follows(User|Oasis $target): bool
    {
        return $this->following()
            ->where('followable_type', $target->getMorphClass())
            ->where('followable_id', $target->getKey())
            ->exists();
    }

    /**
     * Cache for mutual follow user IDs within the current request/model instance.
     *
     * @var array<int>|null
     */
    protected ?array $mutualFollowUserIdsCache = null;

    /**
     * Whether this account and the other user mutually follow each other.
     */
    public function followsMutually(User $other): bool
    {
        if ($this->id === $other->id) {
            return false;
        }

        if ($this->mutualFollowUserIdsCache !== null) {
            return in_array($other->id, $this->mutualFollowUserIdsCache, true);
        }

        return $this->follows($other) && $other->follows($this);
    }

    /**
     * Return (and cache on this model instance) the IDs of users who mutually follow each other with this user.
     *
     * @return array<int>
     */
    public function mutualFollowUserIds(): array
    {
        if ($this->mutualFollowUserIdsCache === null) {
            $this->mutualFollowUserIdsCache = Follow::query()
                ->where('follower_id', $this->id)
                ->where('followable_type', (new User)->getMorphClass())
                ->whereIn('followable_id', function ($query): void {
                    $query->select('follower_id')
                        ->from('follows')
                        ->where('followable_type', (new User)->getMorphClass())
                        ->where('followable_id', $this->id);
                })
                ->pluck('followable_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->mutualFollowUserIdsCache;
    }
}
