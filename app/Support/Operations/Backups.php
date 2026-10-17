<?php

namespace App\Support\Operations;

use App\Models\SystemHeartbeat;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Encrypted database backups and the restore test (docs/oasis-operations.md). A backup is two files below backups/ on
 * `oasis.operations.backup_disk`: <name>.sql.gz.enc (DatabaseDump, encrypted by BackupCipher) and <name>.json (time,
 * row count per table, size and SHA-256 – no personal data). verify() restores the latest backup into the separate
 * database `oasis.operations.verify_database` and compares every row count, then drops it again.
 */
final class Backups
{
    private const DIR = 'backups/';

    /**
     * Create a backup and remove those older than `oasis.operations.backup_keep_days`.
     *
     * @return array{name: string, bytes: int, tables: int, rows: int}
     */
    public function create(): array
    {
        $name = 'oasis-'.now()->utc()->format('Ymd-His');
        $plain = $this->temp();
        $encrypted = $this->temp();
        try {
            $counts = DatabaseDump::write(DB::connection(), $plain);
            rewind($plain);
            BackupCipher::encrypt($plain, $encrypted);
            rewind($encrypted);
            $stat = fstat($encrypted) ?: throw new RuntimeException('Größe der Sicherung unbekannt.');
            $bytes = $stat['size'];
            $hash = hash_init('sha256');
            hash_update_stream($hash, $encrypted);
            rewind($encrypted);
            $this->disk()->writeStream(self::DIR.$name.'.sql.gz.enc', $encrypted);
            $this->disk()->put(self::DIR.$name.'.json', json_encode([
                'name' => $name, 'created_at' => now()->toIso8601String(), 'bytes' => $bytes,
                'sha256' => hash_final($hash), 'tables' => $counts,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            fclose($plain);
            fclose($encrypted);
        }
        $this->prune();
        $summary = ['name' => $name, 'bytes' => $bytes, 'tables' => count($counts), 'rows' => array_sum($counts)];
        SystemHeartbeat::success('backup', $summary);

        return $summary;
    }

    /** Name of the newest complete backup, or null. */
    public function latest(): ?string
    {
        $names = collect($this->disk()->files(rtrim(self::DIR, '/')))
            ->filter(fn (string $file) => str_ends_with($file, '.json'))
            ->map(fn (string $file) => basename($file, '.json'))
            ->filter(fn (string $name) => $this->disk()->exists(self::DIR.$name.'.sql.gz.enc'))
            ->sort()->values();

        return $names->last();
    }

    /**
     * Restore the latest backup into the verify database and compare the row counts with the manifest.
     *
     * @return array{name: string, tables: int, rows: int}
     *
     * @throws RuntimeException when there is no backup, it cannot be read or a count differs
     */
    public function verify(): array
    {
        $name = $this->latest() ?? throw new RuntimeException('Es gibt noch keine Sicherung.');
        $manifest = json_decode((string) $this->disk()->get(self::DIR.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
        $database = str_replace('`', '', (string) config('oasis.operations.verify_database'));
        $main = config('database.connections.'.config('database.default'));
        if ($database === '' || $database === $main['database']) {
            throw new RuntimeException('OASIS_BACKUP_VERIFY_DATABASE muss eine eigene, leere Datenbank sein.');
        }

        $encrypted = $this->disk()->readStream(self::DIR.$name.'.sql.gz.enc') ?? throw new RuntimeException('Sicherung nicht lesbar.');
        $hash = hash_init('sha256');
        $copy = $this->temp();
        stream_copy_to_stream($encrypted, $copy);
        rewind($copy);
        hash_update_stream($hash, $copy);
        if (! hash_equals($manifest['sha256'], hash_final($hash))) {
            throw new RuntimeException('Die Prüfsumme der Sicherung stimmt nicht.');
        }
        rewind($copy);
        $plain = $this->temp();
        try {
            BackupCipher::decrypt($copy, $plain);
            rewind($plain);
            // Own connections: DDL on the main connection would implicitly commit its open transaction.
            Config::set('database.connections.backup_server', ['database' => ''] + $main);
            Config::set('database.connections.backup_verify', ['database' => $database] + $main);
            DB::connection('backup_server')->statement('CREATE DATABASE IF NOT EXISTS `'.$database.'`');
            $verify = DB::connection('backup_verify');
            try {
                DatabaseDump::restore($verify, $plain);
                foreach ($manifest['tables'] as $table => $count) {
                    $restored = in_array($table, DatabaseDump::SKIP_ROWS, true) ? 0 : $verify->table($table)->count();
                    if ($restored !== $count) {
                        throw new RuntimeException('Tabelle '.$table.': '.$restored.' statt '.$count.' Zeilen wiederhergestellt.');
                    }
                }
            } finally {
                DB::purge('backup_verify');
                DB::connection('backup_server')->statement('DROP DATABASE IF EXISTS `'.$database.'`');
                DB::purge('backup_server');
            }
        } finally {
            fclose($copy);
            fclose($plain);
        }
        $summary = ['name' => $name, 'tables' => count($manifest['tables']), 'rows' => array_sum($manifest['tables'])];
        SystemHeartbeat::success('backup-verify', $summary);

        return $summary;
    }

    /** Write the decrypted dump of $name to the local file $path (for a manual restore, docs/oasis-operations.md). */
    public function decryptTo(string $name, string $path): void
    {
        $encrypted = $this->disk()->readStream(self::DIR.basename($name).'.sql.gz.enc') ?? throw new RuntimeException('Sicherung nicht gefunden.');
        if (file_exists($path)) {
            throw new RuntimeException('Die Zieldatei existiert schon; sie wird nie überschrieben.');
        }
        $out = @fopen($path, 'xb') ?: throw new RuntimeException('Die Zieldatei ist nicht beschreibbar.');
        try {
            BackupCipher::decrypt($encrypted, $out);
        } catch (Throwable $failure) {
            fclose($out);
            unlink($path);
            throw $failure;
        }
        fclose($out);
    }

    private function prune(): void
    {
        $cutoff = now()->utc()->subDays((int) config('oasis.operations.backup_keep_days'))->format('Ymd-His');
        foreach ($this->disk()->files(rtrim(self::DIR, '/')) as $file) {
            if (preg_match('/^oasis-(\d{8}-\d{6})\./', basename($file), $m) === 1 && $m[1] < $cutoff) {
                $this->disk()->delete($file);
            }
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('oasis.operations.backup_disk'));
    }

    /** @return resource */
    private function temp()
    {
        return tmpfile() ?: throw new RuntimeException('Kein temporärer Speicher.');
    }
}
