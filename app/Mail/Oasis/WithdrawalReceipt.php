<?php

namespace App\Mail\Oasis;

use App\Models\WithdrawalRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Provides the withdrawal receipt and operator copy sent by WithdrawalController.
 * Confirms receipt of a declaration under §§ 355 ff. BGB, not a completed refund.
 */
class WithdrawalReceipt extends Mailable
{
    /**
     * Bind the saved withdrawal and choose the person or operator wording.
     *
     * @param  bool  $operatorCopy  True for the internal operator copy, false for the person submitting.
     */
    public function __construct(public WithdrawalRequest $submission, public bool $operatorCopy = false)
    {
    }

    /**
     * Distinguish the operator copy from the personal withdrawal receipt.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->operatorCopy ? 'Kopie: Widerruf von '.$this->submission->name : 'Dein Widerruf bei Oasis');
    }

    /**
     * Render the declaration and Berlin receipt time in the withdrawal mail view.
     */
    public function content(): Content
    {
        return new Content(view: 'oasis.mail.withdrawalrequest');
    }
}
