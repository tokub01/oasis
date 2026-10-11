<?php

namespace App\Mail\Oasis;

use App\Models\ContentReport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifies moderation of a saved DSA Art. 16 notice from ContentReportController.
 * This internal request for review is distinct from the reporter acknowledgement.
 */
class ContentReportNotice extends Mailable
{
    /**
     * Bind the saved notice that moderation must review.
     */
    public function __construct(public ContentReport $submission) {}

    /**
     * Identify the mail as a request for content review.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Inhaltsmeldung zur Prüfung bei Oasis');
    }

    /**
     * Render the shared notice view with moderation wording.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.contentreport', with: ['moderationCopy' => true]);
    }
}
