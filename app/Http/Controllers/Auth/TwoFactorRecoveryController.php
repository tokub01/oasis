<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Account\ManageTwoFactorReset;
use App\Http\Controllers\Controller;
use App\Models\TwoFactorReset;
use App\Support\Security\BotProtection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pages of the 2FA recovery for guests (routes two-factor.recovery.*, docs/oasis-accounts.md): request with e-mail and
 * password, then one page per e-mail link that shows the current step – confirm, wait (with countdown), finish – and
 * always offers to cancel. GET never changes anything; every step is a CSRF-protected POST.
 */
class TwoFactorRecoveryController extends Controller
{
    public function __construct(private readonly ManageTwoFactorReset $resets) {}

    public function create(): View
    {
        return view('auth.two-factor-recovery');
    }

    /** Same answer for every input, so the form reveals no accounts. At most 3 requests per address and day. */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        if (BotProtection::check($request->input(BotProtection::FIELD), $request->input(BotProtection::TOKEN)) !== null) {
            return back()->withErrors(['form' => 'Das hat nicht geklappt. Bitte schick das Formular noch einmal ab.']);
        }
        $key = 'two-factor-recovery:'.sha1(mb_strtolower($input['email']));
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 86400);
            $this->resets->request($input['email'], $input['password']);
        }

        return redirect()->route('two-factor.recovery.create')->with('status',
            'Wenn E-Mail-Adresse und Passwort zu einem Konto mit Zwei-Faktor-Anmeldung passen, bekommst du gleich eine E-Mail mit dem nächsten Schritt.');
    }

    public function show(string $token): View
    {
        $reset = $this->resets->find($token);

        return view('auth.two-factor-recovery-step', ['reset' => $reset, 'token' => $token, 'state' => $reset?->state() ?? 'unknown']);
    }

    public function confirm(string $token): RedirectResponse
    {
        $this->resets->confirm($this->reset($token), $token);

        return redirect()->route('two-factor.recovery.show', $token);
    }

    public function complete(string $token): RedirectResponse
    {
        if (! $this->resets->complete($this->reset($token))) {
            return redirect()->route('two-factor.recovery.show', $token);
        }

        return redirect()->route('login')->with('status', 'Die Zwei-Faktor-Anmeldung ist zurückgesetzt. Melde dich mit deinem Passwort an und richte sie gleich wieder ein.');
    }

    public function cancel(string $token): RedirectResponse
    {
        $this->resets->cancel($this->reset($token));

        return redirect()->route('two-factor.recovery.show', $token);
    }

    private function reset(string $token): TwoFactorReset
    {
        return $this->resets->find($token) ?? abort(404);
    }
}
