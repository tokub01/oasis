<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum ComplaintStatus: string
{
    case Open = 'open';
    case Upheld = 'upheld';
    case Overturned = 'overturned';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Upheld => 'Bestätigt',
            self::Overturned => 'Aufgehoben',
        };
    }
}
