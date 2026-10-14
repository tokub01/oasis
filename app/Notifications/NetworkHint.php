<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A database-only network hint with IDs and a bounded excerpt, sent by SendHint. */
class NetworkHint extends Notification
{
    /** @param array<string, int|string|null> $data Minimal event payload, without names or full text. */
    public function __construct(string $id, private readonly array $data)
    {
        $this->id = $id;
    }

    /**
     * No push or immediate email channel.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Store only the already filtered event payload.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }
}
