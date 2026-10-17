<?php

namespace App\Support\Privacy;

/**
 * A readable name for the browser and system of a user agent ("Firefox auf Windows"), shown in the device list and the
 * login alert. Deliberately coarse: it tells a member which device it was without fingerprinting it.
 */
final class DeviceLabel
{
    /** Browser markers in match order: Edge, Opera and Samsung Internet also send "Chrome", Chrome also sends "Safari". */
    private const BROWSERS = [
        'Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser/' => 'Samsung Internet', 'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox', 'CriOS/' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari',
    ];

    /** System markers in match order: iPhones and Android phones also mention "Mac OS X" or "Linux". */
    private const SYSTEMS = [
        'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'Windows' => 'Windows', 'CrOS' => 'ChromeOS',
        'Mac OS X' => 'macOS', 'Linux' => 'Linux',
    ];

    public static function from(?string $userAgent): string
    {
        $userAgent ??= '';

        return self::first(self::BROWSERS, $userAgent, 'Unbekannter Browser').' auf '.self::first(self::SYSTEMS, $userAgent, 'unbekanntem System');
    }

    /** @param  array<string, string>  $markers */
    private static function first(array $markers, string $userAgent, string $fallback): string
    {
        foreach ($markers as $marker => $name) {
            if (str_contains($userAgent, $marker)) {
                return $name;
            }
        }

        return $fallback;
    }
}
