<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum EventType: string
{
    case Opened = 'opened';
    case Assigned = 'assigned';
    case Decided = 'decided';
    case Notified = 'notified';
    case ComplaintReceived = 'complaint_received';
    case ComplaintDecided = 'complaint_decided';
    case Note = 'note';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Opened => 'Eröffnet',
            self::Assigned => 'Zugewiesen',
            self::Decided => 'Entschieden',
            self::Notified => 'Benachrichtigt',
            self::ComplaintReceived => 'Beschwerde eingegangen',
            self::ComplaintDecided => 'Beschwerde entschieden',
            self::Note => 'Notiz',
        };
    }
}
