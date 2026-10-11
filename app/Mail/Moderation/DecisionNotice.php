<?php

namespace App\Mail\Moderation;

use App\Models\ModerationDecision;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** Art. 17 full statement for the affected account; the reporter receives only the outcome and remedies. */
class DecisionNotice extends Mailable
{
    /** Bind a stored decision and the server-selected reporter or subject audience. */
    public function __construct(public ModerationDecision $decision, public string $recipient) {}

    /** Identify the message as a moderation decision. */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Entscheidung zu einer Inhaltsmeldung');
    }

    /** Issue a recipient-bound capability without embedding contact addresses in the URL. */
    public function content(): Content
    {
        return new Content(view: 'moderation.mail.decision', with: [
            'complaintUrl' => URL::signedRoute('moderation.complaint.create', [
                'decision' => $this->decision->id, 'recipient' => $this->recipient,
            ]),
        ]);
    }
}
