<?php

namespace App\Mail\Oasis;

use App\Models\CancellationRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Provides the cancellation receipt and operator copy sent by CancellationController.
 * The retained declaration, receipt time and content support the text-form confirmation under § 312k BGB.
 */
class CancellationReceipt extends Mailable
{
    /**
     * Bind the saved cancellation and choose the person or operator wording.
     *
     * @param  bool  $operatorCopy  True for the internal operator copy, false for the person submitting.
     */
    public function __construct(public CancellationRequest $submission, public bool $operatorCopy = false)
    {
    }

    /**
     * Distinguish the operator copy from the personal cancellation receipt.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->operatorCopy ? 'Kopie: Kündigung von '.$this->submission->name : 'Deine Kündigung bei Oasis');
    }

    /**
     * Render the full declaration and Berlin receipt time in the cancellation mail view.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.cancellationrequest');
    }
}
