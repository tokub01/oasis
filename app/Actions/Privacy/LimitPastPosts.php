<?php

namespace App\Actions\Privacy;

use App\Enums\Network\Visibility;
use App\Models\Post;
use App\Models\User;

/**
 * "Frühere Tropfen begrenzen": every public Tropfen and reply of a member becomes members-only at once. Guests and
 * search engines lose access; members keep reading as before. Cannot be undone in one step – the member makes single
 * Tropfen public again when writing new ones.
 */
class LimitPastPosts
{
    /** @return int number of Tropfen changed */
    public function handle(User $member): int
    {
        return Post::where('user_id', $member->id)->where('visibility', Visibility::Public->value)
            ->update(['visibility' => Visibility::Members->value]);
    }
}
