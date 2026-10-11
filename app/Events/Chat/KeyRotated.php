<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Announces pending/activated versions so chat tabs reload keys and stop stale sends. */
class KeyRotated implements ShouldBroadcastNow
{
    /** Capture the committed state; the event never contains conversation key grants. */
    public function __construct(private readonly string $conversationId, private readonly int $version,
        private readonly bool $pending) {}

    /** Same member-only channel as messages. */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId)];
    }

    /** Stable Echo alias for client integration. */
    public function broadcastAs(): string
    {
        return 'KeyRotated';
    }

    /**
     * Publish only version/rotation metadata.
     *
     * @return array<string, int|string|bool>
     */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'key_version' => $this->version,
            'rotation_required' => $this->pending];
    }
}
