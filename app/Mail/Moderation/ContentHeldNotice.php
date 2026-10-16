<?php

namespace App\Mail\Moderation;

use App\Models\Post;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Art. 17 DSA statement to the author when the automated check holds a Tropfen for review: what happened, that it
 * was detected automatically, that a human decides, and how long it may take. The final decision follows as the usual
 * DecisionNotice with the complaint link.
 */
class ContentHeldNotice extends Mailable
{
    public function __construct(public Post $post) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oasis: Dein Tropfen wird geprüft');
    }

    public function content(): Content
    {
        return new Content(view: 'moderation.mail.held', with: ['postUrl' => route('network.posts.show', $this->post)]);
    }
}
