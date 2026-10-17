<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;

/**
 * Deletes form records (cancellations, withdrawals, content reports, organisation requests) once their retention
 * period `oasis.privacy.records_years` has passed, as promised in the privacy policy.
 *
 * Runs through Laravel's `model:prune` command, scheduled daily in routes/console.php. While the retention period is
 * not decided yet (null), nothing is deleted: the privacy policy then shows "[noch festzulegen]" for this period.
 */
trait PrunedAfterRecordRetention
{
    use MassPrunable;

    /**
     * Records older than the configured retention period, or no records at all while it is not set.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $years = config('oasis.privacy.records_years');

        if ($years === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', now()->subYears((int) $years));
    }
}
