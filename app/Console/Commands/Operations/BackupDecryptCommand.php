<?php

namespace App\Console\Commands\Operations;

use App\Support\Operations\Backups;
use Illuminate\Console\Command;
use Throwable;

/**
 * Decrypts one backup into a local SQL file for a manual restore (docs/oasis-operations.md). Never restores by itself:
 * overwriting the live database stays a deliberate human step.
 */
class BackupDecryptCommand extends Command
{
    protected $signature = 'oasis:backup-decrypt {name : Name der Sicherung, z. B. oasis-20261017-023000} {path : Neue lokale Datei für das SQL}';

    protected $description = 'Sicherung für eine manuelle Wiederherstellung entschlüsseln';

    public function handle(Backups $backups): int
    {
        $name = $this->argument('name');
        $path = $this->argument('path');
        if (! is_string($name) || ! is_string($path)) {
            return self::INVALID;
        }
        try {
            $backups->decryptTo($name, $path);
        } catch (Throwable $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }
        $this->info('Entschlüsselt nach '.$path.'. Die Datei enthält personenbezogene Daten: nach dem Einspielen löschen.');

        return self::SUCCESS;
    }
}
