<?php

namespace App\Mail\Chat;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Du hast eine neue Chat-Nachricht": no name, no text (the chat is end-to-end encrypted), a link and how to switch it off. */
class NewChatMessage extends Mailable
{
    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Du hast eine neue Chat-Nachricht');
    }

    public function content(): Content
    {
        return new Content(view: 'chat.mail.new_message');
    }
}
