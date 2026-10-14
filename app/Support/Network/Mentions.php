<?php

namespace App\Support\Network;

use App\Models\User;

/** Parses bounded @usernames for notifications and the network rich-text component. */
final class Mentions
{
    /** Shared token rule excludes emails and partial matches of too-long usernames. */
    public const PATTERN = '/(?<![\p{L}\p{N}_@.\-+])@([a-z0-9_]{3,30})(?![\p{L}\p{N}_@])/iu';

    /**
     * Return distinct usernames, lowercase and in order of first appearance.
     *
     * @return list<string>
     */
    public static function usernames(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Resolve names once per request, including misses; request attributes cannot leak across workers' requests.
     *
     * @param  list<string>  $names  Usernames without @.
     * @return list<string> Existing lowercase names, in input order.
     */
    public static function existing(array $names): array
    {
        if ($names === []) {
            return [];
        }
        $names = array_values(array_unique(array_map('strtolower', $names)));
        $request = request();
        /** @var array<string, bool> $known */
        $known = $request->attributes->get(self::class.'.existing', []);
        $missing = array_values(array_diff($names, array_keys($known)));
        if ($missing !== []) {
            foreach ($missing as $name) {
                $known[$name] = false;
            }
            foreach (User::whereIn('username', $missing)->pluck('username') as $name) {
                $known[strtolower($name)] = true;
            }
            $request->attributes->set(self::class.'.existing', $known);
        }

        return array_values(array_filter($names, fn (string $name) => $known[$name]));
    }
}
