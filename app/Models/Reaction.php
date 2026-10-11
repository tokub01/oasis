<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quiet reaction to a Tropfen. One per member and Tropfen. The count is shown only to the author ("leise Zahlen");
 * nobody can see who reacted.
 *
 * @property int $post_id
 * @property int $user_id
 */
class Reaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'user_id'];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
