<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Permanently deletes the authenticated account after the route's password confirmation (see DeleteAccount). */
class DeleteAccountController extends Controller
{
    /** Delete the account, then end the browser session. A running membership sends the member back with a hint. */
    public function __invoke(Request $request, DeleteAccount $delete): RedirectResponse
    {
        $user = $request->user();
        $delete->handle($user);

        // Logout after the deletion succeeded; Auth must not save the remember token of the deleted model again.
        Auth::guard('web')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('oasis.home')->with('status', 'Dein Konto wurde gelöscht.');
    }
}
