<?php

namespace App\Support\Mail;

use Illuminate\Mail\Mailables\Headers;

/**
 * List-Unsubscribe headers for mails people subscribed to (RFC 2369 and RFC 8058 one-click): mail programs show an
 * "Abmelden" button and send a POST to the signed URL. Gmail and Yahoo require this for regular mails since 2024;
 * without it they end up in spam. The URL must accept a POST without login or CSRF token (it is signed instead).
 */
final class OneClickUnsubscribe
{
    public static function headers(string $signedPostUrl): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$signedPostUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
