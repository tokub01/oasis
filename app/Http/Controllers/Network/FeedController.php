<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Oasis;
use App\Models\Vote;
use App\Queries\Network\MemberStats;
use App\Queries\Network\PostFeed;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Quelle" (the member's chronological feed with an end, plus a side column with the member's oases, their quiet
 * numbers of the week and a running vote) and "Entdecken" (public Tropfen and oases).
 */
class FeedController extends Controller
{
    public function source(Request $request, PostFeed $feed, MemberStats $stats): View
    {
        $user = $request->user();

        return view('app.home', [
            'posts' => $feed->source($user),
            'myOases' => $user->oases()->withCount('members')->orderBy('name')->get(),
            'followsAnyone' => $user->following()->exists(),
            'week' => $stats->lastDays($user, 7),
            'openVote' => Vote::open()->orderBy('ends_at')->first(),
        ]);
    }

    public function discover(Request $request, PostFeed $feed): View
    {
        return view('app.network.discover', [
            'posts' => $feed->discover($request->user()),
            'oases' => Oasis::withCount('members')->orderByDesc('members_count')->limit(12)->get(),
        ]);
    }
}
