<?php

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Step 4: the second factor is off and every device is signed out. */
class TwoFactorResetCompleted extends Mailable
{
    use Queueable;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Zwei-Faktor-Anmeldung ist zurückgesetzt');
    }

    public function content(): Content
    {
        return new Content(view: 'auth.mail.two_factor_reset_completed');
    }
}
