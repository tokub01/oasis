<?php

namespace App\Mail\Account;

use App\Models\TwoFactorReset;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Step 3: the waiting period started. To the owner with the cancel link ($token); for a staff account also to the
 * operator inbox without a link ($token null).
 */
class TwoFactorResetScheduled extends Mailable
{
    use Queueable;

    public function __construct(public TwoFactorReset $reset, public ?string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->token === null
            ? 'Zwei-Faktor-Rücksetzung für ein Team-Konto angefordert'
            : 'Deine Zwei-Faktor-Anmeldung wird in '.TwoFactorReset::WAIT_HOURS.' Stunden zurückgesetzt');
    }

    public function content(): Content
    {
        return new Content(view: 'auth.mail.two_factor_reset_scheduled', with: [
            'url' => $this->token === null ? null : route('two-factor.recovery.show', $this->token),
        ]);
    }
}
