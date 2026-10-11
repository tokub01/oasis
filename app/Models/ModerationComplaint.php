<?php

namespace App\Models;

use App\Enums\Moderation\ComplaintStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Recipient complaint and the second reviewer’s recorded result under AGB § 12.
 * Used by moderation actions and authorized staff views.
 *
 * @property ComplaintStatus $status
 * @property Carbon $submitted_at
 * @property Carbon|null $reviewed_at
 * @property ModerationCase $case
 * @property ModerationDecision $decision
 * @property User|null $submitter
 * @property User|null $reviewer
 */
#[Fillable(['case_id', 'decision_id', 'submitted_by', 'submitter_email', 'reason', 'status', 'reviewed_by', 'review_explanation', 'submitted_at', 'reviewed_at'])]
class ModerationComplaint extends Model
{
    /**
     * Cast workflow values and dates for the UI and domain actions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * Load the decision relationship for case processing and staff views.
     *
     * @return BelongsTo<ModerationDecision, $this>
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(ModerationDecision::class, 'decision_id');
    }

    /**
     * Load the submitter relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Load the reviewer relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
