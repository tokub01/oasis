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
 * Confirmation for 14-day statutory contract withdrawal (§ 355, 356 BGB).
 */
class MembershipWithdrawnMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  User  $user  The user who withdrew.
     * @param  string  $tierName  Name of the withdrawn membership tier.
     * @param  CarbonInterface|\DateTimeInterface|null  $withdrawnAt  Timestamp of withdrawal declaration.
     * @param  bool  $operatorCopy  Whether this is the internal copy for the operator.
     */
    public function __construct(
        public User $user,
        public string $tierName,
        public CarbonInterface|\DateTimeInterface|null $withdrawnAt = null,
        public bool $operatorCopy = false,
    ) {
        $this->withdrawnAt ??= now();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->operatorCopy
            ? 'Kopie: Widerruf von '.$this->user->display_name.' ('.$this->user->email.')'
            : 'Bestätigung deines Widerrufs bei Oasis';

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'membership.mail.withdrawn',
        );
    }
}
