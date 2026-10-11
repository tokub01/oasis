<?php

namespace App\Queries\Admin;

use App\Models\TransparencyReport;
use App\Models\Vote;

/**
 * Data of the public Transparenz page. The newest published TransparencyReport wins; without one the page falls
 * back to `config('oasis.transparency')` (null values render "Veröffentlichen wir ab dem Start"). Votes come from
 * the database: running ones without results, ended ones with the result.
 */
class PublicTransparency
{
    /**
     * @return array{as_of: string|null, members: int|null, sponsored_seats: int|null, income: float|null,
     *     costs: float|null, cost_items: array<string, float|null>, votes: list<array<string, mixed>>}
     */
    public function data(): array
    {
        $report = TransparencyReport::orderByDesc('month')->first();
        $config = config('oasis.transparency');

        $figures = $report === null ? [
            'as_of' => $config['as_of'],
            'members' => $config['members'],
            'sponsored_seats' => $config['sponsored_seats'],
            'income' => $config['income'],
            'costs' => $config['costs'],
            'cost_items' => $config['cost_items'],
        ] : [
            'as_of' => $report->month->locale('de')->isoFormat('MMMM YYYY'),
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
        return Vote::where('starts_at', '<=', now())->with('options')->orderByDesc('ends_at')->limit(10)->get()
            ->map(fn (Vote $vote) => [
                'title' => $vote->title,
                'open' => $vote->isOpen(),
                'ends' => $vote->ends_at->locale('de')->isoFormat('D. MMMM YYYY'),
                'options' => $vote->options->pluck('label')->all(),
                'result' => $vote->hasEnded() ? $this->result($vote) : null,
            ])->all();
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
