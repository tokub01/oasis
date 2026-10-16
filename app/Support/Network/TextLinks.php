<?php

namespace App\Support\Network;

/** Finds external HTTP links for rich text and the read-before-carry confirmation, without fetching content. */
final class TextLinks
{
    /**
     * Return safe URLs, their visible host and byte offsets in the original text.
     * Credentials and backslashes are excluded to avoid misleading browser destinations.
     *
     * @return list<array{url: string, host: string, position: int}>
     */
    public static function in(string $text): array
    {
        preg_match_all('~(?<![\p{L}\p{N}_:/])https?://[^\s<>"\x27\x00-\x1f\x7f]+~iu', $text, $matches, PREG_OFFSET_CAPTURE);
        $links = [];
        foreach ($matches[0] as [$candidate, $position]) {
            $url = rtrim($candidate, '.,;:!?');
            foreach ([')' => '(', ']' => '[', '}' => '{'] as $closing => $opening) {
                while (str_ends_with($url, $closing) && substr_count($url, $closing) > substr_count($url, $opening)) {
                    $url = rtrim(substr($url, 0, -1), '.,;:!?');
                }
            }
            if (str_contains($url, '\\') || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }
            /** @var array{host: string, user?: string, pass?: string} $parts FILTER_VALIDATE_URL requires a host. */
            $parts = parse_url($url);
            if (isset($parts['user']) || isset($parts['pass'])) {
                continue;
            }
            $links[] = ['url' => $url, 'host' => $parts['host'], 'position' => $position];
        }

        return $links;
    }
}
