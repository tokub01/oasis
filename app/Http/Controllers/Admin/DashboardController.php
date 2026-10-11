<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin statistics module: members & money, network, chat metadata and moderation for a chosen period. */
class DashboardController extends Controller
{
    /** Selectable periods in days. */
    public const PERIODS = [7, 30, 90, 365];

    public function __invoke(Request $request, AdminStats $stats): View
    {
        $days = (int) ($request->validate(['tage' => ['nullable', 'integer', Rule::in(self::PERIODS)]])['tage'] ?? 30);
        $until = CarbonImmutable::now();
        $from = $until->subDays($days);

        return view('admin.dashboard', [
            'days' => $days,
            'members' => $stats->members($from, $until),
            'network' => $stats->network($from, $until),
            'chat' => $stats->chat($from, $until),
            'moderation' => $stats->moderation($from, $until),
            'weekly' => $stats->weekly($until),
        ]);
    }
}
