<?php

namespace App\Listeners\Account;

use App\Actions\Account\ManageTwoFactorReset;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * A sign-in of an account with 2FA has passed the second factor (Fortify fires Login only after the challenge):
 * the owner still has it, so any open recovery request is cancelled – the protection against someone who knows
 * password and mailbox.
 */
class CancelTwoFactorResets
{
    public function __construct(private readonly ManageTwoFactorReset $resets) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $event->user->two_factor_confirmed_at !== null) {
            $this->resets->cancelAllFor($event->user);
        }
    }
}
