<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One violation confirmed by a human moderation decision (one per case). `oasis.moderation.strikes_for_ban` active
 * strikes lead to a permanent ban (App\Actions\Moderation\ApplyAccountConsequences); a successful complaint overturns it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $moderation_case_id
 * @property Carbon|null $overturned_at
 * @property Carbon $created_at
 */
class AccountStrike extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['overturned_at' => 'datetime'];
    }

    /** @return BelongsTo<ModerationCase, $this> */
    public function moderationCase(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class);
    }
}
