<?php

namespace App\Console\Commands\Notifications;

use App\Notifications\NetworkHint;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/** Delete network hints older than 90 days, including unread ones, without touching other notifications. */
class PruneNotifications extends Command
{
    protected $signature = 'oasis:prune-notifications';

    protected $description = 'Delete network hints older than 90 days';

    /** Retention is based on creation, never on when a member last read the hint. */
    public function handle(): int
    {
        $count = DatabaseNotification::where('type', NetworkHint::class)->where('created_at', '<', now()->subDays(90))->delete();
        $this->info($count.' alte Hinweise gelöscht.');

        return self::SUCCESS;
    }
}
