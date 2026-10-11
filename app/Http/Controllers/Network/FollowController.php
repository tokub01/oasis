<?php

namespace App\Http\Controllers\Network;

use App\Actions\Network\ManageFollows;
use App\Http\Controllers\Controller;
use App\Models\Oasis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Follow / unfollow people and oases. Following needs participation and a readable target; unfollowing is always
 * possible (also for read-only accounts).
 */
class FollowController extends Controller
{
    public function __construct(private readonly ManageFollows $follows) {}

    public function followUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('participate');
        abort_unless($user->hasVerifiedEmail() && $user->profileOrDefault()->isVisibleTo($request->user()), 404);
        $this->follows->follow($request->user(), $user);

        return back()->with('status', 'Du folgst jetzt '.$user->display_name.'.');
    }

    public function unfollowUser(Request $request, User $user): RedirectResponse
    {
        $this->follows->unfollow($request->user(), $user);

        return back()->with('status', 'Du folgst '.$user->display_name.' nicht mehr.');
    }

    public function followOasis(Request $request, Oasis $oasis): RedirectResponse
    {
        Gate::authorize('participate');
        Gate::authorize('view', $oasis);
        $this->follows->follow($request->user(), $oasis);

        return back()->with('status', 'Du folgst jetzt der Oase '.$oasis->name.'.');
    }

    public function unfollowOasis(Request $request, Oasis $oasis): RedirectResponse
    {
        $this->follows->unfollow($request->user(), $oasis);

        return back()->with('status', 'Du folgst der Oase '.$oasis->name.' nicht mehr.');
    }
}
