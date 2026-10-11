<?php

namespace App\Mail\Oasis;

use App\Models\WithdrawalRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WithdrawalReceipt extends Mailable
{
    public function __construct(public WithdrawalRequest $submission, public bool $operatorCopy = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->operatorCopy ? 'Kopie: Widerruf von '.$this->submission->name : 'Dein Widerruf bei Oasis');
    }

    public function content(): Content
    {
        return new Content(view: 'oasis.mail.withdrawalrequest');
    }
}
