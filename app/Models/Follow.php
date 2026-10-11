<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A follow of a person ({@see User}) or an oasis ({@see Oasis}). The followers of a person are their "Karawane".
 * `followable_type` holds the model's morph class.
 *
 * @property int $follower_id
 * @property string $followable_type
 * @property int $followable_id
 */
class Follow extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['follower_id', 'followable_type', 'followable_id'];

    /** @return BelongsTo<User, $this> */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /** @return MorphTo<Model, $this> */
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }
}
