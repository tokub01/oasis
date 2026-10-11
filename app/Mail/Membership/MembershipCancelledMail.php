<?php

namespace App\Mail\Membership;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Text-form confirmation for subscription cancellations under § 312k BGB.
 */
class MembershipCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  User  $user  The user who cancelled.
     * @param  string  $tierName  Name of the cancelled membership tier.
     * @param  CarbonInterface|\DateTimeInterface|null  $effectiveDate  The date the subscription ceases access.
     * @param  CarbonInterface|\DateTimeInterface|null  $cancelledAt  Timestamp of cancellation request.
     * @param  bool  $operatorCopy  Whether this is the internal copy for the operator.
     */
    public function __construct(
        public User $user,
        public string $tierName,
        public CarbonInterface|\DateTimeInterface|null $effectiveDate,
        public CarbonInterface|\DateTimeInterface|null $cancelledAt = null,
        public bool $operatorCopy = false,
    ) {
        $this->cancelledAt ??= now();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->operatorCopy
            ? 'Kopie: Kündigung von '.$this->user->display_name.' ('.$this->user->email.')'
            : 'Bestätigung deiner Kündigung bei Oasis';

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'membership.mail.cancelled',
        );
    }
}
