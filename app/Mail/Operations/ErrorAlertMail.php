<?php

namespace App\Mail\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An unexpected error in production (ErrorAlert): where, not what – details only in the server log. */
class ErrorAlertMail extends Mailable
{
    use Queueable;

    public function __construct(public string $class, public string $location, public string $path) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Fehler '.class_basename($this->class));
    }

    public function content(): Content
    {
        return new Content(view: 'operations.mail.error_alert');
    }
}
