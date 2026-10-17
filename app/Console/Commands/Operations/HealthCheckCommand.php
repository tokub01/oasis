<?php

namespace App\Console\Commands\Operations;

use App\Mail\Operations\HealthAlert;
use App\Models\SystemHeartbeat;
use App\Support\Operations\HealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Runs the health check every 15 minutes and mails the operator inbox about failing checks – each check at most every
 * ALERT_HOURS, so a lasting problem does not flood the inbox (docs/oasis-operations.md).
 */
class HealthCheckCommand extends Command
{
    public const ALERT_HOURS = 6;

    protected $signature = 'oasis:health-check';

    protected $description = 'Systemzustand prüfen und bei Problemen per E-Mail warnen';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();
        $due = collect($results)->reject(fn (array $result) => $result['ok'])
            ->filter(function (array $result, string $key): bool {
                $last = SystemHeartbeat::lastSuccess('alert:'.$key);

                return $last === null || $last->lessThan(now()->subHours(self::ALERT_HOURS));
            });
        foreach ($results as $key => $result) {
            $this->line(($result['ok'] ? 'OK      ' : 'PROBLEM ').$result['label'].': '.$result['detail']);
        }
        if ($due->isNotEmpty()) {
            try {
                Mail::to(config('oasis.mail.operator'))->send(new HealthAlert($due->all()));
                $due->keys()->each(fn (string $key) => SystemHeartbeat::success('alert:'.$key));
            } catch (Throwable $failure) {
                report($failure);
            }
        }

        return HealthCheck::healthy($results) ? self::SUCCESS : self::FAILURE;
    }
}
