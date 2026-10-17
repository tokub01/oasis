<?php

namespace App\Mail\Moderation;

use App\Models\AccountSuspension;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Art. 17 DSA statement when an account is locked (provisionally, by decision or permanently): what the member is
 * accused of, how it was detected, what they can still do and that they can answer on /app/gesperrt.
 */
class AccountSuspendedNotice extends Mailable
{
    public function __construct(public AccountSuspension $suspension) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->suspension->type === AccountSuspension::BAN
            ? 'Oasis: Dein Konto ist dauerhaft gesperrt'
            : 'Oasis: Dein Konto ist vorübergehend gesperrt');
    }

    public function content(): Content
    {
        return new Content(view: 'moderation.mail.suspended', with: [
            'strikes' => $this->suspension->user->activeStrikeCount(),
            'limit' => (int) config('oasis.moderation.strikes_for_ban'),
        ]);
    }
}
