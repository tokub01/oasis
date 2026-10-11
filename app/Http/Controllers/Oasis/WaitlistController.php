<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\WaitlistFormRequest;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Handles the public founding waitlist and its signed double-opt-in links in routes/oasis.php.
 * Consent is recorded before confirmation; only a signed confirmation admits the address to the start list.
 */
class WaitlistController extends Controller
{
    /**
     * Keep an existing entry unchanged, or create it safely, then request double opt-in.
     * A uniform redirect avoids disclosing whether an address is already confirmed.
     */
    public function store(WaitlistFormRequest $request): RedirectResponse
    {
        $entry = WaitlistEntry::firstOrCreate(
            ['email' => $request->validated('email')],
            [
                ...$request->safe()->only(['group']),
                'consented_at' => now(),
                'token' => Str::random(64),
            ],
        );

        if ($entry->confirmed_at === null) {
            Mail::to($entry->email)->send(new WaitlistConfirmation($entry));
        }

        return redirect()->route('oasis.bestaetigung.gruendung')->with('oasis.submitted.gruendung', true);
    }

    /**
     * Confirm a signed waitlist entry without replacing its original confirmation time.
     *
     * @param  string  $token  Opaque entry token authenticated by the signed route middleware.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When the token has no entry.
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
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When the token has no entry.
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
}
