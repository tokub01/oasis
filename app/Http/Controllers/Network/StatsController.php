<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Queries\Network\MemberStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Deine Zahlen": the member's own statistics, visible only to them. */
class StatsController extends Controller
{
    public function __invoke(Request $request, MemberStats $stats): View
    {
        $user = $request->user();

        return view('app.network.stats', [
            'totals' => $stats->totals($user),
            'weekly' => $stats->weekly($user),
            'topPosts' => $stats->topPosts($user),
        ]);
    }
}
