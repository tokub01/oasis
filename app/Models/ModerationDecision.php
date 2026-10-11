<?php

namespace App\Models;

use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Human Art. 17 statement of reasons and the notice time controlling its complaint window.
 * Used by moderation actions and authorized staff views.
 *
 * @property Outcome $outcome
 * @property Ground $ground
 * @property Carbon $decided_at
 * @property Carbon|null $notified_at
 * @property ModerationCase $case
 * @property User|null $decider
 */
#[Fillable(['case_id', 'decided_by', 'outcome', 'ground', 'rule_reference', 'facts', 'explanation', 'automated', 'decided_at', 'notified_at', 'is_complaint_review', 'supersedes_decision_id'])]
class ModerationDecision extends Model
{
    /**
     * Cast workflow values and dates for the UI and domain actions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'ground' => Ground::class,
            'automated' => 'boolean',
            'decided_at' => 'datetime',
            'notified_at' => 'datetime',
            'is_complaint_review' => 'boolean',
        ];
    }

    /**
     * Load the case relationship for case processing and staff views.
     *
     * @return BelongsTo<ModerationCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class, 'case_id');
    }

    /**
     * Load the decider relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Load the supersedesDecision relationship for case processing and staff views.
     *
     * @return BelongsTo<ModerationDecision, $this>
     */
    public function supersedesDecision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_decision_id');
    }

    /**
     * Load the complaints relationship for case processing and staff views.
     *
     * @return HasMany<ModerationComplaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(ModerationComplaint::class, 'decision_id');
    }
}
