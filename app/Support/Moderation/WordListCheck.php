<?php

namespace App\Support\Moderation;

/**
 * Safety net of the automated content check: matches whole words from resources/moderation/wortliste.php when the
 * language model is unreachable. A hit scores the category at the report threshold, so a human looks at it;
 * it never reaches the hold threshold (no automatic hiding on a mere word match).
 */
final class WordListCheck
{
    public function verdict(string $text): ContentVerdict
    {
        $words = preg_split('/[^\p{L}]+/u', $this->normalise($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $scores = [];
        foreach (require resource_path('moderation/wortliste.php') as $category => $list) {
            $hits = array_intersect($words, $list);
            $scores[$category] = $hits === [] ? 0.0 : (float) config('oasis.moderation.report_threshold');
        }

        $reason = array_sum($scores) > 0 ? 'Treffer in der Wortliste (KI-Prüfung war nicht erreichbar).' : '';

        return ContentVerdict::fromArray($scores + ['reason' => $reason], 'wordlist');
    }

    /** Lowercase and spell out umlauts so "Arschlöcher"-like spellings and "ae/oe/ue" match the same list. */
    private function normalise(string $text): string
    {
        return strtr(mb_strtolower($text), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    }
}
