<?php

namespace App\Console\Commands\Operations;

use App\Models\SystemHeartbeat;
use App\Support\Operations\Backups;
use Illuminate\Console\Command;
use Throwable;

/** Daily encrypted database backup (docs/oasis-operations.md). A failure is recorded; the health check then alerts. */
class BackupCommand extends Command
{
    protected $signature = 'oasis:backup';

    protected $description = 'Verschlüsselte Datenbank-Sicherung anlegen und alte Sicherungen entfernen';

    public function handle(Backups $backups): int
    {
        try {
            $summary = $backups->create();
        } catch (Throwable $failure) {
            SystemHeartbeat::failure('backup', ['error' => class_basename($failure)]);
            report($failure);
            $this->error('Sicherung fehlgeschlagen: '.$failure->getMessage());

            return self::FAILURE;
        }
        $this->info('Sicherung '.$summary['name'].' angelegt: '.$summary['tables'].' Tabellen, '.$summary['rows'].' Zeilen, '
            .number_format($summary['bytes'] / 1024, 0, ',', '.').' KB.');

        return self::SUCCESS;
    }
}
