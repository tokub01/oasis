<?php

namespace App\Mail\Oasis;

use App\Models\OrganisationRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrganisationNotice extends Mailable
{
    public function __construct(public OrganisationRequest $submission)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Organisationsanfrage für Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'oasis.mail.organisationrequest');
    }
}
