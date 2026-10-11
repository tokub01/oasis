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

class WaitlistController extends Controller
{
    public function store(WaitlistFormRequest $request): RedirectResponse
    {
        $entry = WaitlistEntry::firstOrNew(['email' => $request->validated('email')]);

        if (! $entry->exists) {
            $entry->fill([
                ...$request->safe()->only(['group']),
                'consented_at' => now(),
                'token' => Str::random(64),
            ])->save();
        }

        if ($entry->confirmed_at === null) {
            Mail::to($entry->email)->send(new WaitlistConfirmation($entry));
        }

        return redirect()->route('oasis.bestaetigung.gruendung')->with('oasis.submitted.gruendung', true);
    }

    public function confirm(string $token): View
    {
        $entry = WaitlistEntry::where('token', $token)->firstOrFail();
        if ($entry->confirmed_at === null) {
            $entry->update(['confirmed_at' => now()]);
        }

        return view('oasis.bestaetigung.startliste');
    }

    public function unsubscribe(string $token): View
    {
        WaitlistEntry::where('token', $token)->firstOrFail();

        return view('oasis.bestaetigung.abmelden', ['token' => $token]);
    }

    public function destroy(string $token): View
    {
        WaitlistEntry::where('token', $token)->delete();

        return view('oasis.bestaetigung.abgemeldet');
    }
}
