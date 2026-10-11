<?php

namespace App\Queries\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\ComplaintStatus;
use App\Enums\Moderation\Priority;
use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\ModerationEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Overview at the top of the moderation queue: what needs attention now, how fast the team decides, and the latest
 * activity. Staff-internal; reporter contact data is never loaded.
 */
class QueueOverview
{
    public function __construct(private readonly TransparencyQuery $transparency) {}

    /**
     * @return array{active: int, unassigned: int, urgent: int, mine: int, complaints: int, decided_week: int,
     *     oldest_waiting: CarbonImmutable|null, median_seconds: float|int|null}
     */
    public function figures(User $staff): array
    {
        $waiting = [CaseStatus::Open->value, CaseStatus::InReview->value];
        $active = ModerationCase::whereIn('status', [...$waiting, CaseStatus::ComplaintOpen->value]);
        $oldest = ModerationCase::whereIn('status', $waiting)->min('created_at');

        return [
            'active' => (clone $active)->count(),
            'unassigned' => (clone $active)->whereNull('assigned_to')->count(),
            'urgent' => (clone $active)->where('priority', Priority::Urgent->value)->count(),
            'mine' => (clone $active)->where('assigned_to', $staff->id)->count(),
            'complaints' => ModerationComplaint::where('status', ComplaintStatus::Open->value)->count(),
            'decided_week' => ModerationCase::where('status', CaseStatus::Decided->value)->where('updated_at', '>=', now()->subDays(7))->count(),
            'oldest_waiting' => $oldest === null ? null : CarbonImmutable::parse($oldest),
            'median_seconds' => $this->transparency->forPeriod(now()->subDays(30), now())['median_time_to_decision_seconds'],
        ];
    }

    /**
     * The latest audit events across all cases, newest first.
     *
     * @return Collection<int, ModerationEvent>
     */
    public function recentActivity(int $limit = 8): Collection
    {
        return ModerationEvent::with('actor')->orderByDesc('id')->limit($limit)->get();
    }
}
