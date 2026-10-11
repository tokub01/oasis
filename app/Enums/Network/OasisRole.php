<?php

namespace App\Enums\Network;

/** Role inside one oasis. Hosts edit the oasis and enforce its house rules. */
enum OasisRole: string
{
    case Host = 'host';
    case Member = 'member';

    /** German label (gender-neutral, as on the public pages). */
    public function label(): string
    {
        return match ($this) {
            self::Host => 'Gastgeber:in',
            self::Member => 'Mitglied',
        };
    }
}
