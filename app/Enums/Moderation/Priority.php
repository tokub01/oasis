<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum Priority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgent => 'Dringend',
        };
    }
}
