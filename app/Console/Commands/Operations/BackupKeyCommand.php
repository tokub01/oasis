<?php

namespace App\Console\Commands\Operations;

use App\Support\Operations\BackupCipher;
use Illuminate\Console\Command;

/** Prints a new backup key. It is not stored anywhere: put it into .env and a password manager (docs/oasis-operations.md). */
class BackupKeyCommand extends Command
{
    protected $signature = 'oasis:backup-key';

    protected $description = 'Neuen Schlüssel für die Sicherungen erzeugen (wird nur angezeigt)';

    public function handle(): int
    {
        $this->line('OASIS_BACKUP_KEY='.BackupCipher::generateKey());
        $this->warn('In die .env eintragen und zusätzlich außerhalb des Servers aufbewahren – ohne ihn sind die Sicherungen unlesbar.');

        return self::SUCCESS;
    }
}
