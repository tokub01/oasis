<?php

namespace App\Mail\Oasis;

use App\Models\CancellationRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CancellationReceipt extends Mailable
{
    public function __construct(public CancellationRequest $submission, public bool $operatorCopy = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->operatorCopy ? 'Kopie: Kündigung von '.$this->submission->name : 'Deine Kündigung bei Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'oasis.mail.cancellationrequest');
    }
}
