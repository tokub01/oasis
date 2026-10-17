<?php

namespace App\Mail\Notifications;

use App\Models\User;
use App\Support\Mail\OneClickUnsubscribe;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * A quiet, opt-in daily count; no names, post contents or snippets leave by email. Can be switched off from the mail
 * without signing in, also with the one-click button of mail programs (List-Unsubscribe, RFC 8058).
 */
class DailyDigest extends Mailable
{
    /** Count only currently readable, unread hints in the digest window. */
    public function __construct(public readonly int $count, public readonly User $user) {}

    public function headers(): Headers
    {
        return OneClickUnsubscribe::headers(URL::signedRoute('notifications.digest.one-click', ['user' => $this->user]));
    }

    /** Render the summary with private inbox links and the signed link to switch it off. */
    public function build(): static
    {
        return $this->subject('Deine Hinweise bei Oasis')->view('app.notifications.digest', [
            'unsubscribeUrl' => URL::signedRoute('notifications.digest.unsubscribe', ['user' => $this->user]),
        ]);
    }
}
