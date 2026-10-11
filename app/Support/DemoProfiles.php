<?php

namespace App\Support;

/**
 * Sample profiles for the public guest preview (/profil/{handle}).
 *
 * Oasis has no real accounts yet, so the profile page renders one of these fixed samples and labels the page as
 * "Beispielansicht" (see config `oasis.demo_pages`). Unknown handles are not invented: they produce a 404.
 * Replace this class with a real profile repository once accounts exist.
 */
class DemoProfiles
{
    /**
     * All sample profiles, keyed by lowercase handle.
     *
     * @var array<string, array{name: string, first_name: string, initials: string, bio: string, location: string,
     *     since: string, founding_member: bool, oasen: list<array{name: string, role: string, color: string}>}>
     */
    private const PROFILES = [
        'mira' => [
            'name' => 'Mira Sol',
            'first_name' => 'Mira',
            'initials' => 'MS',
            'bio' => 'Fotografiert analog, meistens zu früh am Morgen. Gastgeberin der Oase Korn & Licht.',
            'location' => 'Leipzig',
            'since' => 'März 2026',
            'founding_member' => true,
            // `color` is a Tailwind background class for the oasis badge.
            'oasen' => [
                ['name' => 'Korn & Licht', 'role' => 'Gastgeberin', 'color' => 'bg-sun'],
                ['name' => 'Lesekreis', 'role' => 'Mitglied', 'color' => 'bg-night'],
            ],
        ],
    ];

    /**
     * Find a sample profile by handle (case-insensitive).
     *
     * @return array<string, mixed>|null The profile, or null when no sample exists for this handle.
     */
    public static function find(string $handle): ?array
    {
        return self::PROFILES[strtolower($handle)] ?? null;
    }
}
