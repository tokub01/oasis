<?php

namespace App\Enums;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum Role: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Member => 'Mitglied',
            self::Moderator => 'Moderation',
            self::Admin => 'Admin',
        };
    }

    /** Identify staff roles for navigation and authorization. */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }
}
