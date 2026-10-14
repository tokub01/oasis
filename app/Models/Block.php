<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A block between two accounts.
 *
 * Wer blockiert, und wer blockiert wird, sehen gegenseitig keine Tropfen, Antworten
 * und Profile mehr (404), können sich nicht folgen, nicht antworten, nicht weitertragen,
 * nicht reagieren, keine Oasen-Rollen ändern und keinen Chat starten.
 *
 * @property int $id
 * @property int $user_id
 * @property int $blocked_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $blocker
 * @property User $blocked
 */
class Block extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'blocked_user_id',
    ];

    /**
     * The account that placed the block.
     *
     * @return BelongsTo<User, $this>
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The account that is blocked.
     *
     * @return BelongsTo<User, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
