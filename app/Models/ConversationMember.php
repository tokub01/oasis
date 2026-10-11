<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership history used by chat authorization and current membership rotation.
 *
 * @property CarbonImmutable $joined_at
 * @property CarbonImmutable|null $left_at
 * @property User $user
 */
class ConversationMember extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Membership timestamps establish access boundaries after rejoining.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime'];
    }

    /**
     * Resolve the public account metadata for member responses.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
