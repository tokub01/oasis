<?php

namespace App\Mail\Oasis;

use App\Models\ContentReport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContentReportNotice extends Mailable
{
    public function __construct(public ContentReport $submission)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Inhaltsmeldung zur Prüfung bei Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'oasis.mail.contentreport', with: ['moderationCopy' => true]);
    }
}
