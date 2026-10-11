<?php

namespace App\Queries\Moderation;

use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use Carbon\CarbonInterface;

/** Aggregated moderation statistics without reporter contacts, ready for the future transparency page. */
class TransparencyQuery
{
    /**
     * Use a half-open interval [from, until); measure first decisions separately from complaint replacements.
     *
     * @return array{notices_received: int, decisions_by_outcome: array<string, int>, decisions_by_ground: array<string, int>, complaints_received: int, complaints_overturned: int, median_time_to_decision_seconds: float|int|null}
     */
    public function forPeriod(CarbonInterface $from, CarbonInterface $until): array
    {
        $decisions = ModerationDecision::where('decided_at', '>=', $from)->where('decided_at', '<', $until);
        $firstDecisions = (clone $decisions)->where('is_complaint_review', false)->with('case')->get();
        $durations = $firstDecisions->map(fn (ModerationDecision $decision) => $decision->case->created_at->diffInSeconds($decision->decided_at))->sort()->values();
        $complaints = ModerationComplaint::where('submitted_at', '>=', $from)->where('submitted_at', '<', $until);

        return [
            'notices_received' => ModerationCase::where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
            'decisions_by_outcome' => (clone $decisions)->selectRaw('outcome, COUNT(*) AS total')->groupBy('outcome')->pluck('total', 'outcome')->map(fn ($count) => (int) $count)->all(),
            'decisions_by_ground' => (clone $decisions)->selectRaw('ground, COUNT(*) AS total')->groupBy('ground')->pluck('total', 'ground')->map(fn ($count) => (int) $count)->all(),
            'complaints_received' => $complaints->count(),
            'complaints_overturned' => ModerationComplaint::where('status', 'overturned')
                ->where('reviewed_at', '>=', $from)->where('reviewed_at', '<', $until)->count(),
            'median_time_to_decision_seconds' => $durations->median(),
        ];
    }
}
