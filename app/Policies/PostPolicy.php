<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tropfen permissions. Reading follows {@see Post::isVisibleTo()}; every write additionally needs the stage 1
 * participation gate `participate` (active trial or membership), so read-only accounts can still read and export.
 */
class PostPolicy
{
    /** Guests (null) included: public Tropfen are readable without an account. */
    public function view(?User $user, Post $post): bool
    {
        return $post->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && Gate::forUser($user)->allows('participate');
    }

    /** Reply, react and carry further ("weitertragen") need a readable Tropfen that is still in place. */
    public function interact(User $user, Post $post): bool
    {
        return $this->create($user)
            && $post->removed_from_oasis_at === null
            && $post->isVisibleTo($user)
            && ! in_array($post->user_id, $user->blockedUserIds(), true);
    }

    /** Authors delete their own Tropfen at any time, also read-only accounts (data minimisation). */
    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    /** Hosts remove Tropfen from their oasis when they break the house rules. */
    public function removeFromOasis(User $user, Post $post): bool
    {
        return $post->oasis !== null && $post->removed_from_oasis_at === null && $post->oasis->isHost($user);
    }

    /** Reaction counts are "leise Zahlen": only the author sees them. */
    public function viewStats(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
