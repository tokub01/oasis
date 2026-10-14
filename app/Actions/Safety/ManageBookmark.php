<?php

namespace App\Actions\Safety;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Manages private bookmarks for Tropfen.
 *
 * Bookmarks are strictly private: nobody sees who bookmarked what.
 * Bookmarking requires that the Tropfen is visible to the member.
 */
class ManageBookmark
{
    /**
     * Bookmark a Tropfen.
     *
     * @throws ValidationException
     */
    public function bookmark(User $user, Post $post): Bookmark
    {
        if (! $post->isVisibleTo($user)) {
            abort(404);
        }

        return Bookmark::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Remove a bookmark on a Tropfen.
     */
    public function remove(User $user, Post $post): void
    {
        Bookmark::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();
    }
}
