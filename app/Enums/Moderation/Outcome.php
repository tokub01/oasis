<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum Outcome: string
{
    case NoAction = 'no_action';
    case ContentRemoved = 'content_removed';
    case VisibilityRestricted = 'visibility_restricted';
    case AccountRestricted = 'account_restricted';
    case AccountSuspended = 'account_suspended';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::NoAction => 'Keine Maßnahme',
            self::ContentRemoved => 'Inhalt entfernt',
            self::VisibilityRestricted => 'Sichtbarkeit eingeschränkt',
            self::AccountRestricted => 'Konto eingeschränkt',
            self::AccountSuspended => 'Konto gesperrt',
        };
    }
}
