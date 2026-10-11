<?php

namespace App\Enums\Network;

/** Background of the initials avatar (design tokens), chosen by the member. */
enum AvatarColor: string
{
    case Sun = 'sun';
    case Spring = 'spring';
    case Night = 'night';
    case Dune = 'dune';

    /** Tailwind classes for background and text, matching x-oasis.post. */
    public function classes(): string
    {
        return match ($this) {
            self::Sun => 'bg-sun text-sun-ink',
            self::Spring => 'bg-spring text-sun-ink',
            self::Night => 'bg-night text-on-night',
            self::Dune => 'bg-dune-line text-night',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sun => 'Sonne',
            self::Spring => 'Quelle',
            self::Night => 'Nacht',
            self::Dune => 'Düne',
        };
    }
}
