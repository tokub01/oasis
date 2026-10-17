<?php

namespace App\Support\Moderation;

use App\Models\ChatReport;
use App\Models\ContentCheck;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;

/**
 * The content a moderation case is about: the checked text of an automated check, the reported message of a chat
 * report, or – for a public report – the Tropfen, profile or oasis its link points to on this site. Links to other
 * hosts or unknown paths resolve to nothing.
 */
final class CaseSubject
{
    public static function resolve(ModerationCase $case): Post|Profile|Oasis|ChatReport|null
    {
        $source = $case->source;

        return match (true) {
            $source instanceof ContentCheck => self::content($source->checkable),
            $source instanceof ChatReport => $source,
            $source instanceof ContentReport => self::fromUrl($source->url),
            default => null,
        };
    }

    /** Resolve a link to this site: /tropfen/{id}, /profil/{handle} or /oasen/{slug}. */
    public static function fromUrl(string $url): Post|Profile|Oasis|null
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && strcasecmp($host, (string) parse_url(config('app.url'), PHP_URL_HOST)) !== 0) {
            return null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            preg_match('#^/tropfen/(\d+)#', $path, $m) === 1 => Post::find((int) $m[1]),
            preg_match('#^/profil/([A-Za-z0-9_]{1,30})#', $path, $m) === 1 => User::where('username', mb_strtolower($m[1]))->first()?->profile,
            preg_match('#^/oasen/([a-z0-9-]+)#', $path, $m) === 1 => Oasis::where('slug', $m[1])->first(),
            default => null,
        };
    }

    private static function content(mixed $checkable): Post|Profile|Oasis|null
    {
        return $checkable instanceof Post || $checkable instanceof Profile || $checkable instanceof Oasis ? $checkable : null;
    }
}
