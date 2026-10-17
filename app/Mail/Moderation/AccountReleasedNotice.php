<?php

namespace App\Mail\Moderation;

use App\Models\AccountSuspension;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the member that the lock has ended, and whether a violation was recorded (counts towards a ban). */
class AccountReleasedNotice extends Mailable
{
    public function __construct(public AccountSuspension $suspension) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Dein Konto ist wieder freigegeben');
    }

    public function content(): Content
    {
        return new Content(view: 'moderation.mail.released', with: [
            'strikes' => $this->suspension->user->activeStrikeCount(),
            'limit' => (int) config('oasis.moderation.strikes_for_ban'),
        ]);
    }
}
