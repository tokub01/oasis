<?php

namespace App\Mail\Privacy;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a member about a sign-in from a browser or system their account has not used before (RecordLoginDevice). */
class LoginAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $device, public CarbonInterface $at) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Anmeldung bei Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'privacy.mail.login_alert');
    }
}
