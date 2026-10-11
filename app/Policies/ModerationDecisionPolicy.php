<?php

namespace App\Policies;

use App\Models\ContentReport;
use App\Models\ModerationDecision;
use App\Models\User;

/** Recipient capabilities and authenticated affected users may complain for six months after notice. */
class ModerationDecisionPolicy
{
    /** The signed recipient must match a stored party, and the decision must still be current. */
    public function submitComplaint(?User $user, ModerationDecision $decision, string $recipient): bool
    {
        $case = $decision->case;
        $reporter = $case->source instanceof ContentReport ? $case->source->email : null;

        return $decision->notified_at !== null
            && now()->lte($decision->notified_at->copy()->addMonthsNoOverflow(6))
            && $case->latestDecision?->id === $decision->id
            && in_array($recipient, array_filter([$reporter, $case->subjectUser?->email]), true);
    }
}
