<?php

namespace App\Actions\Network;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;

/** Persists quiet reactions for authorized network interactions; withdrawing remains possible in read-only mode. */
class ManageReaction
{
    /** Keep at most one reaction per account and post. */
    public function react(User $user, Post $post): void
    {
        Reaction::firstOrCreate(['post_id' => $post->id, 'user_id' => $user->id]);
    }

    /** Withdraw only this account's own reaction. */
    public function remove(User $user, Post $post): void
    {
        Reaction::where('post_id', $post->id)->where('user_id', $user->id)->delete();
    }
}
