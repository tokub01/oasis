<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A private bookmark on a Tropfen.
 *
 * Tropfen privat merken und entfernen (nur für Mitglieder). Niemand sieht,
 * wer was gemerkt hat – auch Autor:innen nicht.
 *
 * @property int $id
 * @property int $user_id
 * @property int $post_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 * @property Post $post
 */
class Bookmark extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'post_id',
    ];

    /**
     * The account that saved the bookmark.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The bookmarked Tropfen.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
