<?php

namespace App\Mail\Oasis;

use App\Models\OrganisationRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sends an organisation enquiry to the operator from OrganisationController.
 * Contains planning information and the reply address without creating a membership contract.
 */
class OrganisationNotice extends Mailable
{
    /**
     * Bind the saved organisation enquiry for operator follow-up.
     */
    public function __construct(public OrganisationRequest $submission) {}

    /**
     * Identify the mail as a new organisation enquiry.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Organisationsanfrage für Oasis');
    }

    /**
     * Render the enquiry details and Berlin receipt time for the operator.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.organisationrequest');
    }
}
