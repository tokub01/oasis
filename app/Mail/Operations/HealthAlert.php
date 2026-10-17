<?php

namespace App\Mail\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the operator inbox which health checks fail (oasis:health-check). Sent directly, never queued: the queue may be
 * the very thing that is broken.
 */
class HealthAlert extends Mailable
{
    use Queueable;

    /** @param  array<string, array{label: string, ok: bool, detail: string}>  $problems */
    public function __construct(public array $problems) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: '.count($this->problems).' Problem'.(count($this->problems) === 1 ? '' : 'e').' im Betrieb');
    }

    public function content(): Content
    {
        return new Content(view: 'operations.mail.health_alert');
    }
}
