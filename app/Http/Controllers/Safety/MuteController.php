<?php

namespace App\Http\Controllers\Safety;

use App\Actions\Safety\ManageMute;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Muting and unmuting accounts.
 */
class MuteController extends Controller
{
    /**
     * Mute an account.
     */
    public function store(Request $request, User $user, ManageMute $manage): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Du kannst dich nicht selbst stummschalten.');

        $manage->mute($request->user(), $user);

        return back()->with('status', $user->display_name.' ist stummgeschaltet. Die Person merkt nichts.');
    }

    /**
     * Unmute an account.
     */
    public function destroy(Request $request, User $user, ManageMute $manage): RedirectResponse
    {
        $manage->unmute($request->user(), $user);

        return back()->with('status', 'Stummschaltung von '.$user->display_name.' aufgehoben.');
    }
}
