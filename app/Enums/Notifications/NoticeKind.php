<?php

namespace App\Enums\Notifications;

/** The six independently configurable network hints, used by settings and delivery. */
enum NoticeKind: string
{
    case Follow = 'follow';
    case Reply = 'reply';
    case Carry = 'carry';
    case Mention = 'mention';
    case Invitation = 'invitation';
    case Host = 'host';

    /** German setting label, also usable as a fallback for a deleted resource. */
    public function label(): string
    {
        return match ($this) {
            self::Follow => 'Neue Person in deiner Karawane',
            self::Reply => 'Antwort auf deinen Tropfen',
            self::Carry => 'Dein Tropfen wird weitergetragen',
            self::Mention => 'Erwähnung in einem Tropfen',
            self::Invitation => 'Deine Einladung wurde angenommen',
            self::Host => 'Du wirst Gastgeber:in einer Oase',
        };
    }
}
