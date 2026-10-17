<?php

namespace App\Actions\Account;

use App\Actions\Privacy\EndSessions;
use App\Mail\Account\TwoFactorResetCompleted;
use App\Mail\Account\TwoFactorResetLink;
use App\Mail\Account\TwoFactorResetScheduled;
use App\Models\TwoFactorReset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Recovering an account whose second factor is lost (docs/oasis-accounts.md, "Zwei-Faktor verloren"). Three proofs
 * and time: the password, access to the e-mail address, and a waiting period of TwoFactorReset::WAIT_HOURS with a
 * notice and a cancel link – plenty of time for the real owner to stop someone who knows password and mailbox.
 * Signing in with the second factor in between cancels every open request (CancelTwoFactorResets on Login).
 * Staff resets also alert the operator inbox.
 */
class ManageTwoFactorReset
{
    public function __construct(private readonly EndSessions $sessions) {}

    /**
     * Start a request when e-mail and password match an account with 2FA. Returns nothing either way, so the page
     * can answer identically and reveal no accounts; earlier open requests of the account are replaced.
     */
    public function request(string $email, string $password): void
    {
        $user = User::where('email', mb_strtolower(trim($email)))->first();
        // Check a password even without an account, so the answer time reveals nothing either.
        $matches = Hash::check($password, $user->password ?? self::dummyHash());
        if ($user === null || $user->two_factor_confirmed_at === null || ! $matches) {
            return;
        }
        $token = Str::random(48);
        DB::transaction(function () use ($user, $token): void {
            $this->openRequests($user)->update(['cancelled_at' => now()]);
            (new TwoFactorReset)->forceFill([
                'user_id' => $user->id,
                'token_hash' => TwoFactorReset::hashToken($token),
                'link_expires_at' => now()->addHours(TwoFactorReset::LINK_HOURS),
            ])->save();
        });
        Mail::to($user->email)->send(new TwoFactorResetLink($user, $token));
    }

    public function find(string $token): ?TwoFactorReset
    {
        return TwoFactorReset::where('token_hash', TwoFactorReset::hashToken($token))->first();
    }

    /** The e-mail link was opened: the waiting period starts, the owner gets a notice with a cancel link. */
    public function confirm(TwoFactorReset $reset, string $token): void
    {
        if ($reset->state() !== 'requested') {
            return;
        }
        $reset->forceFill(['confirmed_at' => now(), 'available_at' => now()->addHours(TwoFactorReset::WAIT_HOURS)])->save();
        Mail::to($reset->user->email)->send(new TwoFactorResetScheduled($reset, $token));
        if ($reset->user->role->isStaff()) {
            Mail::to(config('oasis.mail.operator'))->send(new TwoFactorResetScheduled($reset, null));
        }
    }

    /** After the waiting period: switch 2FA off, sign out everywhere, tell the owner. */
    public function complete(TwoFactorReset $reset): bool
    {
        if ($reset->state() !== 'available') {
            return false;
        }
        $user = $reset->user;
        DB::transaction(function () use ($reset, $user): void {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
            $reset->forceFill(['completed_at' => now()])->save();
            // Every session ends (no current one is spared) and "Angemeldet bleiben" is renewed.
            $this->sessions->others($user, '');
        });
        Mail::to($user->email)->send(new TwoFactorResetCompleted($user));

        return true;
    }

    public function cancel(TwoFactorReset $reset): void
    {
        if (in_array($reset->state(), ['requested', 'waiting', 'available'], true)) {
            $reset->forceFill(['cancelled_at' => now()])->save();
        }
    }

    /** The owner signed in with the second factor: nothing is lost, every open request ends. */
    public function cancelAllFor(User $user): void
    {
        $this->openRequests($user)->update(['cancelled_at' => now()]);
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(32));
    }

    /** @return Builder<TwoFactorReset> */
    private function openRequests(User $user)
    {
        return TwoFactorReset::where('user_id', $user->id)->whereNull('completed_at')->whereNull('cancelled_at');
    }
}
