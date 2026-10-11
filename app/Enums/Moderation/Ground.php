<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum Ground: string
{
    case IllegalContent = 'illegal_content';
    case CommunityRules = 'community_rules';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::IllegalContent => 'Rechtswidriger Inhalt',
            self::CommunityRules => 'Community-Regeln',
        };
    }
}
