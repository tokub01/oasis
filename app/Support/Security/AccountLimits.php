<?php

namespace App\Support\Security;

use App\Models\User;
use App\Support\Network\Mentions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Graduated limits against spam accounts (docs/oasis-bot-protection.md). Accounts younger than
 * `oasis.bot_protection.new_account_hours` (24) may do less per hour and put fewer links and mentions into a Tropfen;
 * everyone is limited for follows and new chats, and nobody can post the same text twice within ten minutes.
 * Each refusal is a German validation error at the given field and is counted (BotProtection::count).
 */
final class AccountLimits
{
    /** Hourly budgets: action => [new account, established account]; null = no extra hourly limit. */
    public const HOURLY = [
        'posts' => [10, null],
        'follows' => [30, 300],
        'chats' => [3, 30],
    ];

    public const NEW_MAX_LINKS = 2;

    public const NEW_MAX_MENTIONS = 3;

    public const DUPLICATE_MINUTES = 10;

    public static function isNew(User $user): bool
    {
        return $user->created_at !== null
            && $user->created_at->greaterThan(now()->subHours((int) config('oasis.bot_protection.new_account_hours')));
    }

    /**
     * Count one action and refuse it when the hourly budget is used up.
     *
     * @throws ValidationException at $field
     */
    public static function hit(User $user, string $action, string $field): void
    {
        [$new, $established] = self::HOURLY[$action];
        $max = self::isNew($user) ? $new : $established;
        if ($max === null) {
            return;
        }
        $key = 'account-limit:'.$action.':'.$user->id;
        if (RateLimiter::tooManyAttempts($key, $max)) {
            BotProtection::count('limit_'.$action);
            throw ValidationException::withMessages([$field => self::isNew($user)
                ? 'Neue Konten können in der ersten Zeit etwas weniger auf einmal. Bitte warte ein wenig und versuche es dann noch einmal.'
                : 'Das war gerade sehr viel auf einmal. Bitte warte ein wenig und versuche es dann noch einmal.']);
        }
        RateLimiter::hit($key, 3600);
    }

    /**
     * Check a Tropfen text before it is published.
     *
     * @throws ValidationException at 'body'
     */
    public static function checkText(User $user, string $body): void
    {
        if (Cache::has(self::duplicateKey($user, $body))) {
            BotProtection::count('duplicate');
            throw ValidationException::withMessages(['body' => 'Diesen Text hast du gerade schon geteilt.']);
        }
        if (! self::isNew($user)) {
            return;
        }
        if (preg_match_all('~(https?://|www\.)~i', $body) > self::NEW_MAX_LINKS) {
            BotProtection::count('content_links');
            throw ValidationException::withMessages(['body' => 'In den ersten '.config('oasis.bot_protection.new_account_hours').' Stunden kannst du höchstens '.self::NEW_MAX_LINKS.' Links pro Tropfen teilen.']);
        }
        if (count(Mentions::usernames($body)) > self::NEW_MAX_MENTIONS) {
            BotProtection::count('content_mentions');
            throw ValidationException::withMessages(['body' => 'In den ersten '.config('oasis.bot_protection.new_account_hours').' Stunden kannst du höchstens '.self::NEW_MAX_MENTIONS.' Menschen pro Tropfen erwähnen.']);
        }
    }

    /** Remember a published text, so the same text is refused for DUPLICATE_MINUTES. */
    public static function remember(User $user, string $body): void
    {
        Cache::put(self::duplicateKey($user, $body), true, now()->addMinutes(self::DUPLICATE_MINUTES));
    }

    private static function duplicateKey(User $user, string $body): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($body)) ?? '', 'UTF-8');

        return 'recent-text:'.$user->id.':'.sha1($normalized);
    }
}
