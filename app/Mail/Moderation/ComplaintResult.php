<?php

namespace App\Mail\Moderation;

use App\Models\ModerationComplaint;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** German complaint result with the second reviewer's reasons and remaining remedies. */
class ComplaintResult extends Mailable
{
    /** Bind the persisted complaint after the review transaction commits. */
    public function __construct(public ModerationComplaint $complaint) {}

    /** Identify the result of the internal complaint procedure. */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Ergebnis deiner Beschwerde');
    }

    /** Render the result from the retained review explanation. */
    public function content(): Content
    {
        return new Content(view: 'moderation.mail.complaint-result');
    }
}
