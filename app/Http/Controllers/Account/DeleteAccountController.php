<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Permanently deletes the authenticated account after the route's password confirmation. */
class DeleteAccountController extends Controller
{
    /** Remove account/reset tokens/sessions and invalidate the current browser session. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        // Logout rotates and saves the remember token; do this before deleting to avoid recreating the model.
        Auth::guard('web')->logout();
        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('oasis.home')->with('status', 'Dein Konto wurde gelöscht.');
    }
}
