<?php

namespace App\Mail\Account;

use App\Models\TwoFactorReset;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Step 2 of the 2FA recovery: proves access to the e-mail address (link valid TwoFactorReset::LINK_HOURS). */
class TwoFactorResetLink extends Mailable
{
    use Queueable;

    public function __construct(public User $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zwei-Faktor-Anmeldung zurücksetzen');
    }

    public function content(): Content
    {
        return new Content(view: 'auth.mail.two_factor_reset_link', with: [
            'url' => route('two-factor.recovery.show', $this->token), 'hours' => TwoFactorReset::LINK_HOURS,
        ]);
    }
}
