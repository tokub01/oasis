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

    /** Follows this account made (of people and oases). @return HasMany<Follow, $this> */
    public function following(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /** Follows of this account: its "Karawane". @return MorphMany<Follow, $this> */
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
}
