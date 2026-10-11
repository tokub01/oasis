<?php

namespace App\Mail\Oasis;

use App\Models\WaitlistEntry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * Sends the double-opt-in request for WaitlistController::store.
 * Includes a seven-day signed confirmation URL and a signed unsubscribe entry point.
 */
class WaitlistConfirmation extends Mailable
{
    /**
     * Bind the saved, unconfirmed waitlist entry to the double-opt-in request.
     */
    public function __construct(public WaitlistEntry $entry) {}

    /**
     * Use a fixed subject that requests verification without claiming membership.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätige deine E-Mail-Adresse für Oasis');
    }

    /**
     * Provide the waitlist view with signed links; the unsubscribe GET only opens a confirmation step.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.waitlist', with: [
            'confirmUrl' => URL::temporarySignedRoute('oasis.waitlist.confirm', now()->addDays(7), ['token' => $this->entry->token]),
            'unsubscribeUrl' => URL::signedRoute('oasis.waitlist.unsubscribe', ['token' => $this->entry->token]),
        ]);
    }
}
