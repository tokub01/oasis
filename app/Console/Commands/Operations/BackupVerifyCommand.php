<?php

namespace App\Console\Commands\Operations;

use App\Models\SystemHeartbeat;
use App\Support\Operations\Backups;
use Illuminate\Console\Command;
use Throwable;

/**
 * Weekly restore test: the latest backup is decrypted, restored into its own database and compared table by table.
 * Only a backup that was restored successfully counts (docs/oasis-operations.md).
 */
class BackupVerifyCommand extends Command
{
    protected $signature = 'oasis:backup-verify';

    protected $description = 'Neueste Sicherung testweise wiederherstellen und prüfen';

    public function handle(Backups $backups): int
    {
        try {
            $summary = $backups->verify();
        } catch (Throwable $failure) {
            SystemHeartbeat::failure('backup-verify', ['error' => class_basename($failure)]);
            report($failure);
            $this->error('Wiederherstellungstest fehlgeschlagen: '.$failure->getMessage());

            return self::FAILURE;
        }
        $this->info('Sicherung '.$summary['name'].' wiederhergestellt und geprüft: '.$summary['tables'].' Tabellen, '.$summary['rows'].' Zeilen.');

        return self::SUCCESS;
    }
}
