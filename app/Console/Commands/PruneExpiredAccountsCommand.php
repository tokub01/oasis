<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Scheduled command to prune accounts whose retention period has passed after trial end or cancellation.
 * Guarded by config('oasis.retention.auto_delete_expired') which defaults to false.
 */
class PruneExpiredAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oasis:prune-expired-accounts {--force : Force pruning even if config switch is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune content of expired accounts past retention_days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $enabled = config('oasis.retention.auto_delete_expired', false) || $this->option('force');
        $retentionDays = (int) config('oasis.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        // Find candidate accounts: trial ended before cutoff, no active subscription, no active gift, member role
        $candidateQuery = User::query()
            ->where('role', Role::Member)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $cutoff)
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->whereIn('status', ['active', 'canceled'])->where('paid_until', '>', now());
            })
            ->whereDoesntHave('giftMemberships', function ($query) {
                $query->where('expires_at', '>', now());
            });

        $count = $candidateQuery->count();

        if (! $enabled) {
            $this->info('Automatic account pruning is disabled (oasis.retention.auto_delete_expired is false).');
            $this->info("Found {$count} expired accounts past the {$retentionDays}-day retention period.");

            return self::SUCCESS;
        }

        $this->warn("Pruning {$count} expired accounts past {$retentionDays}-day retention window...");

        $deletedCount = 0;
        foreach ($candidateQuery->get() as $user) {
            // Delete account
            $user->delete();
            $deletedCount++;
        }

        $this->info("Pruned {$deletedCount} expired accounts.");

        return self::SUCCESS;
    }
}
