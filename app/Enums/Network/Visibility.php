<?php

namespace App\Enums\Network;

/** Who may read a profile, an oasis or a Tropfen. */
enum Visibility: string
{
    /** Readable by guests without an account and shareable by link. */
    case Public = 'public';
    /** Readable only by signed-in members with a verified email address. */
    case Members = 'members';

    /** German label for forms. */
    public function label(): string
    {
        return match ($this) {
            self::Public => 'Öffentlich – auch ohne Konto lesbar',
            self::Members => 'Nur für Mitglieder',
        };
    }
}
