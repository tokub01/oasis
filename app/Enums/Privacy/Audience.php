<?php

namespace App\Enums\Privacy;

/**
 * Who a privacy setting lets in. Each setting offers only the audiences that make sense for it
 * (see App\Models\PrivacySetting::AUDIENCES); "Karawane" is not offered because anyone can follow without consent.
 */
enum Audience: string
{
    /** Everyone, also guests without an account. */
    case Everyone = 'everyone';
    /** Signed-in members with a verified email address. */
    case Members = 'members';
    /** Only people the member follows – a circle the member chooses alone. */
    case Following = 'following';
    /** Friends (accepted friend requests) and people the member follows. */
    case Contacts = 'contacts';
    /** Nobody besides the member. */
    case Nobody = 'nobody';

    /** German label for the privacy center. */
    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Alle – auch ohne Konto',
            self::Members => 'Alle Mitglieder',
            self::Following => 'Menschen, denen du folgst',
            self::Contacts => 'Freund:innen und Menschen, denen du folgst',
            self::Nobody => 'Niemand',
        };
    }
}
