<?php

namespace App\Mail\Moderation;

use App\Models\ModerationCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alerts the moderation inbox to an escalated case. Contains no content and no names – only the type and a link. */
class EscalationAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ModerationCase $case) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Eskalierter Fall #'.$this->case->id.': '.$this->case->escalation?->label());
    }

    public function content(): Content
    {
        return new Content(view: 'moderation.mail.escalation_alert');
    }
}
