<?php

namespace App\Http\Controllers\Safety;

use App\Actions\Safety\ManageBlock;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Blocking and unblocking accounts.
 */
class BlockController extends Controller
{
    /**
     * Block an account.
     */
    public function store(Request $request, User $user, ManageBlock $manage): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Du kannst dich nicht selbst blockieren.');

        $manage->block($request->user(), $user);

        return back()->with('status', 'Du hast '.$user->display_name.' blockiert. Ihr seht einander nicht mehr.');
    }

    /**
     * Unblock an account.
     */
    public function destroy(Request $request, User $user, ManageBlock $manage): RedirectResponse
    {
        $manage->unblock($request->user(), $user);

        return back()->with('status', 'Blockade von '.$user->display_name.' aufgehoben.');
    }
}
