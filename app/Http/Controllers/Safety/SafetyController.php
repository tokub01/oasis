<?php

namespace App\Http\Controllers\Safety;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Mute;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Overview of safety controls: blocked accounts and muted accounts.
 *
 * Route safety.index (/app/sicherheit).
 */
class SafetyController extends Controller
{
    /**
     * Show the lists of blocked and muted accounts with unblock/unmute options.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $blockedUsers = User::query()
            ->whereIn('id', Block::query()->where('user_id', $viewer->id)->select('blocked_user_id'))
            ->with('profile')
            ->orderBy('display_name')
            ->get();

        $mutedUsers = User::query()
            ->whereIn('id', Mute::query()->where('user_id', $viewer->id)->select('muted_user_id'))
            ->with('profile')
            ->orderBy('display_name')
            ->get();

        return view('app.safety.index', [
            'blockedUsers' => $blockedUsers,
            'mutedUsers' => $mutedUsers,
        ]);
    }
}
