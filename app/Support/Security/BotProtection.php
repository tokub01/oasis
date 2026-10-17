<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Bot traps for public forms without captcha or tracking (docs/oasis-bot-protection.md):
 * - a honeypot field humans never see or fill;
 * - a time trap: the form carries an encrypted timestamp; a submission faster than MIN_SECONDS or older than
 *   MAX_HOURS is refused (bots post instantly, or replay old forms).
 * Refusals are counted per day and reason (no personal data) and shown on the admin system page.
 */
final class BotProtection
{
    public const FIELD = 'website';

    public const TOKEN = 'form_token';

    public const MIN_SECONDS = 3;

    public const MAX_HOURS = 24;

    public const REASONS = [
        'honeypot' => 'Unsichtbares Feld ausgefüllt',
        'too_fast' => 'Formular zu schnell abgeschickt',
        'token' => 'Formular ohne gültige Zeitmarke',
        'disposable_email' => 'Wegwerf-E-Mail-Adresse',
        'registration_ip' => 'Zu viele Registrierungen von einer Adresse',
        'limit_posts' => 'Neues Konto: zu viele Tropfen',
        'limit_follows' => 'Zu viele Follows',
        'limit_chats' => 'Zu viele neue Chats',
        'content_links' => 'Neues Konto: zu viele Links',
        'content_mentions' => 'Neues Konto: zu viele Erwähnungen',
        'duplicate' => 'Gleicher Text mehrfach',
    ];

    public static function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /** The reason a submission looks automated, or null for a human one. Counted when not null. */
    public static function check(mixed $honeypot, mixed $token): ?string
    {
        $issued = is_string($token) ? self::issuedAt($token) : null;
        $age = $issued === null ? null : now()->getTimestamp() - $issued;
        $timeTrap = config('oasis.bot_protection.time_trap') === true;
        $reason = match (true) {
            is_string($honeypot) && $honeypot !== '' => 'honeypot',
            $timeTrap && ($age === null || $age > self::MAX_HOURS * 3600) => 'token',
            $timeTrap && $age < self::MIN_SECONDS => 'too_fast',
            default => null,
        };
        if ($reason !== null) {
            self::count($reason);
        }

        return $reason;
    }

    public static function count(string $reason): void
    {
        $key = 'bot-protection:'.now()->toDateString().':'.$reason;
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);
    }

    /** @return array<string, int> today's refusals per reason */
    public static function today(): array
    {
        return collect(array_keys(self::REASONS))
            ->mapWithKeys(fn (string $reason) => [$reason => (int) Cache::get('bot-protection:'.now()->toDateString().':'.$reason, 0)])
            ->filter()->all();
    }

    private static function issuedAt(string $token): ?int
    {
        try {
            $value = Crypt::decryptString($token);
        } catch (Throwable) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
