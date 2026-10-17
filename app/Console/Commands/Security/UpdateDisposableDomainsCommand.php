<?php

namespace App\Console\Commands\Security;

use App\Support\Security\DisposableEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Refreshes the list of throwaway e-mail domains from the open project disposable-email-domains (CC0) into
 * storage/app (the bundled copy in resources/data stays as fallback). Runs monthly; a download that looks wrong (too
 * short, invalid lines) is ignored and the previous list stays.
 */
class UpdateDisposableDomainsCommand extends Command
{
    public const SOURCE = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

    public const MIN_DOMAINS = 1000;

    protected $signature = 'oasis:disposable-domains-update';

    protected $description = 'Liste der Wegwerf-E-Mail-Domains aktualisieren';

    public function handle(): int
    {
        try {
            $body = Http::timeout(20)->get(self::SOURCE)->throw()->body();
        } catch (Throwable $failure) {
            $this->error('Download fehlgeschlagen ('.class_basename($failure).'); die bisherige Liste bleibt.');

            return self::FAILURE;
        }
        $lines = array_values(array_filter(array_map('trim', explode("\n", strtolower($body))), fn (string $line) => $line !== ''));
        $valid = array_filter($lines, fn (string $line) => preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $line) === 1);
        if (count($valid) < self::MIN_DOMAINS || count($valid) !== count($lines)) {
            $this->error('Die geladene Liste sieht nicht richtig aus ('.count($valid).' von '.count($lines).' Zeilen gültig); die bisherige Liste bleibt.');

            return self::FAILURE;
        }
        file_put_contents(storage_path(DisposableEmail::UPDATED), implode("\n", $valid)."\n", LOCK_EX);
        DisposableEmail::flush();
        $this->info(count($valid).' Wegwerf-Domains gespeichert.');

        return self::SUCCESS;
    }
}
