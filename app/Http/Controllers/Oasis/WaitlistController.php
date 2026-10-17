<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The signed double-opt-in links of the founding start list (sign-up: App\Livewire\Oasis\WaitlistSignup).
 * Only a signed confirmation admits an address to the start list; unsubscribing needs a signed POST.
 */
class WaitlistController extends Controller
{
    /**
     * Confirm a signed waitlist entry without replacing its original confirmation time.
     *
     * @param  string  $token  Opaque entry token authenticated by the signed route middleware.
     *
     * @throws ModelNotFoundException When the token has no entry.
     */
    public function confirm(string $token): View
    {
        $entry = WaitlistEntry::where('token', $token)->firstOrFail();
        if ($entry->confirmed_at === null) {
            $entry->update(['confirmed_at' => now()]);
        }

        return view('oasis.bestaetigung.startliste');
    }

    /**
     * Show an explicit unsubscribe step so mail-scanner GET requests cannot delete entries.
     *
     * @param  string  $token  Opaque entry token authenticated by the signed route middleware.
     *
     * @throws ModelNotFoundException When the token has no entry.
     */
    public function unsubscribe(string $token): View
    {
        WaitlistEntry::where('token', $token)->firstOrFail();

        return view('oasis.bestaetigung.abmelden', ['token' => $token]);
    }

    /**
     * Delete the entry after a signed, CSRF-protected POST; repeated deletion is harmless.
     *
     * @param  string  $token  Opaque entry token authenticated by the signed route middleware.
     */
    public function destroy(string $token): View
    {
        WaitlistEntry::where('token', $token)->delete();

        return view('oasis.bestaetigung.abgemeldet');
    }

    /**
     * The POST mail programs send for the List-Unsubscribe header (RFC 8058); no CSRF token, the URL is signed.
     *
     * @param  string  $token  Opaque entry token authenticated by the signed route middleware.
     */
    public function oneClick(string $token): Response
    {
        WaitlistEntry::where('token', $token)->delete();

        return response('Du bist von der Oasis-Startliste abgemeldet.', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
