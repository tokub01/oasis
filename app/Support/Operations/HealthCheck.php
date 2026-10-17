<?php

namespace App\Support\Operations;

use App\Models\SystemHeartbeat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks everything Oasis needs to keep its promises (docs/oasis-operations.md): database, cache, media storage, free
 * disk space, the queue, the scheduler (deletion periods, Mollie sync, mails depend on it), the Mollie sync, the
 * backup and the restore test. Each result says only what is wrong – never personal data – so it can be shown on the
 * token-protected /gesundheit URL for uptime monitors.
 */
final class HealthCheck
{
    /** Minutes a queued job may wait before the queue counts as stuck. */
    public const QUEUE_MINUTES = 15;

    /**
     * @return array<string, array{label: string, ok: bool, detail: string}>
     */
    public function run(): array
    {
        return [
            'database' => $this->guard('Datenbank', function (): string {
                DB::select('select 1');

                return 'erreichbar';
            }),
            'cache' => $this->guard('Cache', function (): string {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 60);
                $ok = Cache::pull($key) === 'ok';

                return $ok ? 'schreibt und liest' : throw new HealthProblem('Cache liefert den geschriebenen Wert nicht.');
            }),
            'storage' => $this->guard('Bilder-Speicher', function (): string {
                $disk = Storage::disk(config('oasis.media_disk'));
                $path = 'health/'.Str::random(12).'.txt';
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                return $ok ? 'schreibt und liest ('.config('oasis.media_disk').')' : throw new HealthProblem('Speicher liefert die Datei nicht.');
            }),
            'disk' => $this->guard('Freier Plattenplatz', function (): string {
                $free = (int) disk_free_space(storage_path());
                $gigabytes = round($free / 1024 ** 3, 1);

                return $free >= 1024 ** 3 ? $gigabytes.' GB frei' : throw new HealthProblem('Nur noch '.$gigabytes.' GB frei.');
            }),
            'queue' => $this->guard('Warteschlange', function (): string {
                $stuck = DB::table('jobs')->where('available_at', '<', now()->subMinutes(self::QUEUE_MINUTES)->getTimestamp())->count();
                $failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();
                if ($stuck > 0) {
                    throw new HealthProblem($stuck.' Aufträge warten länger als '.self::QUEUE_MINUTES.' Minuten – läuft der Queue-Worker?');
                }

                return $failed > 0 ? throw new HealthProblem($failed.' fehlgeschlagene Aufträge in 24 Stunden.') : 'läuft';
            }),
            'scheduler' => $this->fresh('Zeitplaner (Löschfristen, Abgleich, Mails)', 'scheduler', 5),
            'mollie-sync' => $this->fresh('Mollie-Abgleich', 'mollie-sync', 30),
            'backup' => $this->fresh('Datenbank-Sicherung', 'backup', 26 * 60),
            'backup-verify' => $this->fresh('Wiederherstellungstest', 'backup-verify', 8 * 24 * 60),
        ];
    }

    /** @param  array<string, array{label: string, ok: bool, detail: string}>  $results */
    public static function healthy(array $results): bool
    {
        return collect($results)->every(fn (array $result) => $result['ok']);
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function fresh(string $label, string $key, int $maxMinutes): array
    {
        $last = SystemHeartbeat::lastSuccess($key);
        $ok = $last instanceof CarbonInterface && $last->greaterThan(now()->subMinutes($maxMinutes));

        return ['label' => $label, 'ok' => $ok,
            'detail' => $last === null ? 'noch nie erfolgreich gelaufen' : 'zuletzt erfolgreich '.$last->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr'];
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function guard(string $label, callable $check): array
    {
        try {
            return ['label' => $label, 'ok' => true, 'detail' => $check()];
        } catch (Throwable $failure) {
            return ['label' => $label, 'ok' => false, 'detail' => $failure instanceof HealthProblem ? $failure->getMessage() : 'nicht erreichbar ('.class_basename($failure).')'];
        }
    }
}
