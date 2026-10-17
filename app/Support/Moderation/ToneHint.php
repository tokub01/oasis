<?php

namespace App\Support\Moderation;

/**
 * Finds a disrespectful slang address in a draft (resources/moderation/umgangston.php) for a short, non-blocking hint
 * with a link to the rules ("Umgangston"). It is a nudge only: nothing is reported, held or counted. The chat client
 * applies the same list in the browser (resources/js/chat, `toneHint`), because chat texts never reach the server.
 */
final class ToneHint
{
    /** The first matching word as written in the text, or null. */
    public static function find(string $text): ?string
    {
        $words = require resource_path('moderation/umgangston.php');
        $always = implode('|', array_map(fn (string $word) => preg_quote($word, '/'), $words['always']));
        $address = implode('|', array_map(fn (string $word) => preg_quote($word, '/'), $words['address']));
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue']);

        if (preg_match('/(?<![\p{L}])('.$always.')(?![\p{L}])/iu', $text, $match) === 1) {
            return $match[1];
        }
        // An address: at the start of a sentence followed by a comma or !/?, or set off by a comma at the end.
        if (preg_match('/(?:^|[.!?]\s+)('.$address.')\s*[,!?]/iu', $text, $match) === 1
            || preg_match('/,\s*('.$address.')\s*[.!?]*\s*$/iu', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
