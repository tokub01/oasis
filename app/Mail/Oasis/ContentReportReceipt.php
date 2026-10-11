<?php

namespace App\Mail\Oasis;

use App\Models\ContentReport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Acknowledges a saved DSA Art. 16 notice to its reporter from ContentReportController.
 * Receipt is confirmed without promising a particular moderation outcome.
 */
class ContentReportReceipt extends Mailable
{
    /**
     * Bind the saved notice to the reporter acknowledgement.
     */
    public function __construct(public ContentReport $submission) {}

    /**
     * Identify the mail as the personal notice acknowledgement.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Meldung bei Oasis');
    }

    /**
     * Render the shared notice view with reporter wording.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.contentreport', with: ['moderationCopy' => false]);
    }
}
