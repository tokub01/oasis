<?php

namespace App\Mail\Oasis;

use App\Models\WaitlistEntry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class WaitlistConfirmation extends Mailable
{
    public function __construct(public WaitlistEntry $entry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätige deine E-Mail-Adresse für Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'oasis.mail.waitlist', with: [
            'confirmUrl' => URL::temporarySignedRoute('oasis.waitlist.confirm', now()->addDays(7), ['token' => $this->entry->token]),
            'unsubscribeUrl' => URL::signedRoute('oasis.waitlist.unsubscribe', ['token' => $this->entry->token]),
        ]);
    }
}
