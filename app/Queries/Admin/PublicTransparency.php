<?php

namespace App\Queries\Admin;

use App\Models\TransparencyReport;
use App\Models\Vote;
use App\Models\VoteOption;
use App\Queries\Membership\MembershipStats;

/**
 * Data of the public Transparenz page, real figures only. The newest published TransparencyReport wins (a monthly
 * snapshot of the memberships plus the costs an admin entered); until the first one, the page shows today's live
 * figures and no costs (rendered as "Veröffentlichen wir ab dem Start"). Votes come from the database: running ones
 * without results, ended ones with the result.
 */
class PublicTransparency
{
    public function __construct(private readonly MembershipStats $membership) {}

    /**
     * @return array{as_of: string|null, members: int|null, sponsored_seats: int|null, income: float|null,
     *     costs: float|null, cost_items: array<string, float|null>, votes: list<array<string, mixed>>}
     */
    public function data(): array
    {
        $report = TransparencyReport::orderByDesc('month')->first();
        $month = $report?->month;
        $month?->locale('de');
        $today = now('Europe/Berlin');
        $today->locale('de');

        $figures = $report === null ? [
            'as_of' => 'heute, '.$today->isoFormat('D. MMMM YYYY'),
            'members' => $this->membership->activeMembersCount(),
            'sponsored_seats' => $this->membership->sponsoredSeats(),
            'income' => $this->membership->monthlyRecurringRevenue(),
            'costs' => null,
            'cost_items' => array_fill_keys(config('oasis.transparency.cost_items'), null),
        ] : [
            'as_of' => $month?->isoFormat('MMMM YYYY'),
            'members' => $report->members,
            'sponsored_seats' => $report->sponsored_seats,
            'income' => (float) $report->income,
            'costs' => $report->costs(),
            'cost_items' => $report->cost_items,
        ];

        return $figures + ['votes' => $this->votes()];
    }

    /** @return list<array{title: string, open: bool, ends: string, options: list<string>, result: string|null}> */
    private function votes(): array
    {
        return array_values(Vote::where('starts_at', '<=', now())->with('options')->orderByDesc('ends_at')->limit(10)->get()
            ->map(function (Vote $vote): array {
                $ends = $vote->ends_at;
                $ends->locale('de');

                return [
                    'title' => $vote->title,
                    'open' => $vote->isOpen(),
                    'ends' => $ends->isoFormat('D. MMMM YYYY'),
                    'options' => array_values($vote->options->map(fn (VoteOption $option): string => $option->label)->all()),
                    'result' => $vote->hasEnded() ? $this->result($vote) : null,
                ];
            })->all());
    }

    private function result(Vote $vote): string
    {
        $total = $vote->options->sum('votes_count');
        if ($total === 0) {
            return 'keine Stimmen abgegeben';
        }

        return $vote->options->map(fn ($option) => $option->label.' '.(int) round($option->votes_count / $total * 100).' %')
            ->implode(', ').' ('.$total.' Stimmen)';
    }
}
