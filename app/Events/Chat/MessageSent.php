<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Relays a previously persisted encrypted envelope; no model serialization or plaintext. */
class MessageSent implements ShouldBroadcastNow
{
    /**
     * Snapshot the validated envelope so later model changes cannot alter the broadcast.
     *
     * @param array<string, mixed> $envelope
     */
    public function __construct(private readonly array $envelope) {}

    /** Only the conversation's authorized private channel receives ciphertext. */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->envelope['conversation_id'])];
    }

    /** Stable Echo alias independent of PHP namespaces. */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Never include the message model, identity, key grants or sender profile.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->envelope;
    }
}
