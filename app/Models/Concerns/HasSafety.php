<?php

namespace App\Models\Concerns;

use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Mute;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Safety relations and checks for accounts: bidirectional blocks,
 * unidirectional mutes, and private bookmarks.
 */
trait HasSafety
{
    /** @var array<int>|null Memoized bidirectional blocked IDs for request performance. */
    protected ?array $safetyBlockedUserIds = null;

    /** @var array<int>|null Memoized unidirectional muted IDs for request performance. */
    protected ?array $safetyMutedUserIds = null;

    /**
     * Blocks initiated by this account.
     *
     * @return HasMany<Block, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'user_id');
    }

    /**
     * Blocks placed on this account by other accounts.
     *
     * @return HasMany<Block, $this>
     */
    public function blockedBy(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_user_id');
    }

    /**
     * Mutes initiated by this account.
     *
     * @return HasMany<Mute, $this>
     */
    public function mutes(): HasMany
    {
        return $this->hasMany(Mute::class, 'user_id');
    }

    /**
     * Bookmarks saved by this account.
     *
     * @return HasMany<Bookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'user_id');
    }

    /**
     * Tropfen saved by this account.
     *
     * @return BelongsToMany<Post, $this>
     */
    public function bookmarkedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bookmarks')
            ->withTimestamps();
    }

    /**
     * True if either account has blocked the other (bidirectional).
     */
    public function isBlockedWith(User $other): bool
    {
        if ($this->id === $other->id) {
            return false;
        }

        return in_array($other->id, $this->blockedUserIds(), true);
    }

    /**
     * IDs with which a block exists in either direction.
     *
     * @return array<int>
     */
    public function blockedUserIds(bool $fresh = false): array
    {
        if ($this->safetyBlockedUserIds !== null && ! $fresh) {
            return $this->safetyBlockedUserIds;
        }

        $blockedByMe = Block::query()
            ->where('user_id', $this->id)
            ->pluck('blocked_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $blockingMe = Block::query()
            ->where('blocked_user_id', $this->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->safetyBlockedUserIds = array_values(array_unique(array_merge($blockedByMe, $blockingMe)));
    }

    /**
     * IDs that this account has muted (one direction only).
     *
     * @return array<int>
     */
    public function mutedUserIds(bool $fresh = false): array
    {
        if ($this->safetyMutedUserIds !== null && ! $fresh) {
            return $this->safetyMutedUserIds;
        }

        return $this->safetyMutedUserIds = Mute::query()
            ->where('user_id', $this->id)
            ->pluck('muted_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Determine whether this user has explicitly blocked another account.
     */
    public function hasBlocked(User $other): bool
    {
        return in_array($other->id, $this->blockedUserIds(), true)
            && Block::query()
                ->where('user_id', $this->id)
                ->where('blocked_user_id', $other->id)
                ->exists();
    }

    /**
     * Determine whether this user has muted another account.
     */
    public function hasMuted(User $other): bool
    {
        return in_array($other->id, $this->mutedUserIds(), true);
    }

    /**
     * Determine whether this user has bookmarked a specific post.
     */
    public function hasBookmarked(Post $post): bool
    {
        return Bookmark::query()
            ->where('user_id', $this->id)
            ->where('post_id', $post->id)
            ->exists();
    }

    /**
     * Reset the in-memory memoized IDs for blocks and mutes.
     */
    public function clearSafetyCache(): void
    {
        $this->safetyBlockedUserIds = null;
        $this->safetyMutedUserIds = null;
    }
}
