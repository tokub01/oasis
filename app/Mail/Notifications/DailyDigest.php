<?php

namespace App\Mail\Notifications;

use Illuminate\Mail\Mailable;

/** A quiet, opt-in daily count; no names, post contents or snippets leave by email. */
class DailyDigest extends Mailable
{
    /** Count only currently readable, unread hints in the digest window. */
    public function __construct(public readonly int $count) {}

    /** Render the summary with private inbox/settings links. */
    public function build(): static
    {
        return $this->subject('Deine Hinweise bei Oasis')->view('app.notifications.digest');
    }
}
