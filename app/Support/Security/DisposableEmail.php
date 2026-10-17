<?php

namespace App\Support\Security;

/**
 * Recognises throwaway e-mail addresses (docs/oasis-bot-protection.md). The list comes from the open project
 * disposable-email-domains (CC0): bundled in resources/data, refreshed monthly by `oasis:disposable-domains-update`
 * into storage/app. A domain counts also with every subdomain ("x.mailinator.com"). `oasis.bot_protection.
 * allowed_email_domains` overrides the list for false positives.
 */
final class DisposableEmail
{
    public const BUNDLED = 'resources/data/disposable-email-domains.txt';

    public const UPDATED = 'app/disposable-email-domains.txt';

    /** @var array<string, true>|null */
    private static ?array $domains = null;

    public static function isDisposable(string $email): bool
    {
        $domain = mb_strtolower(trim(substr((string) strrchr($email, '@'), 1)), 'UTF-8');
        if ($domain === '' || in_array($domain, config('oasis.bot_protection.allowed_email_domains', []), true)) {
            return false;
        }
        $domains = self::domains();
        $parts = explode('.', $domain);
        while (count($parts) >= 2) {
            if (isset($domains[implode('.', $parts)])) {
                return true;
            }
            array_shift($parts);
        }

        return false;
    }

    /** Forget the loaded list (after an update, and between tests). */
    public static function flush(): void
    {
        self::$domains = null;
    }

    /** @return array<string, true> */
    private static function domains(): array
    {
        if (self::$domains === null) {
            $updated = storage_path(self::UPDATED);
            $file = is_file($updated) ? $updated : base_path(self::BUNDLED);
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$domains = array_fill_keys(array_map(fn (string $line) => strtolower(trim($line)), $lines), true);
        }

        return self::$domains;
    }
}
