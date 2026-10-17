<?php

namespace App\Livewire\Admin;

use App\Queries\Admin\AdminStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin statistics on /admin (route admin.dashboard): members and money, network, chat metadata and moderation for
 * a chosen period, plus the money received per calendar month. Sums only, never people or content. Admins only (route
 * middleware, checked again on every render).
 */
class Dashboard extends Component
{
    /** Selectable periods in days. */
    public const PERIODS = [7, 30, 90, 365];

    /** Period in days, kept in the URL (?tage=…); anything else falls back to 30. */
    #[Url(as: 'tage')]
    public int $days = 30;

    /** Calendar month of the revenue card as YYYY-MM (?monat=…); empty, invalid or future values mean this month. */
    #[Url(as: 'monat')]
    public string $month = '';

    /** Browse the revenue card by one month back (-1) or forward (+1), never past the current month. */
    public function shiftMonth(int $step): void
    {
        abort_unless(in_array($step, [-1, 1], true), 422);
        $month = $this->revenueMonth()->addMonths($step);
        $this->month = $month->greaterThanOrEqualTo($this->currentMonth()) ? '' : $month->format('Y-m');
    }

    public function thisMonth(): void
    {
        $this->month = '';
    }

    /** Figures for the chosen period; an invalid period from the URL falls back to 30 days. */
    public function render(AdminStats $stats): View
    {
        Gate::authorize('administrate');
        if (! in_array($this->days, self::PERIODS, true)) {
            $this->days = 30;
        }
        $until = CarbonImmutable::now();
        $from = $until->subDays($this->days);

        return view('livewire.admin.dashboard', [
            'members' => $stats->members($from, $until),
            'network' => $stats->network($from, $until),
            'chat' => $stats->chat($from, $until),
            'moderation' => $stats->moderation($from, $until),
            'weekly' => $stats->weekly($until),
            'revenueMonth' => $month = $this->revenueMonth(),
            'revenue' => $stats->revenue($month),
            'revenuePrevious' => $stats->revenue($month->subMonth()),
            'isCurrentMonth' => $month->equalTo($this->currentMonth()),
        ])->layout('layouts.staff', ['title' => 'Statistik', 'active' => 'admin']);
    }

    /** First day of the chosen month in German time. */
    private function revenueMonth(): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $this->month, 'Europe/Berlin');
            if ($month !== null && $month->lessThan($this->currentMonth()) && $month->year >= 2020) {
                return $month;
            }
        }

        return $this->currentMonth();
    }

    private function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::now('Europe/Berlin')->startOfMonth();
    }
}
