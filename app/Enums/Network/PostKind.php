<?php

namespace App\Enums\Network;

/**
 * The kind of a Tropfen: regular post or a question to one's followers ("Frage an meine Karawane").
 */
enum PostKind: string
{
    /** Regular short post. */
    case Post = 'post';

    /** A question addressed to one's followers ("Karawane"). */
    case Question = 'question';

    /** German label for forms and badges. */
    public function label(): string
    {
        return match ($this) {
            self::Post => 'Tropfen',
            self::Question => 'Frage an meine Karawane',
        };
    }
}
