<?php

namespace App\Console\Commands;

use App\Actions\Account\DeleteAccount;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Scheduled command to prune accounts whose retention period has passed after trial end or cancellation.
 * Guarded by config('oasis.retention.auto_delete_expired') which defaults to false.
 *
 * The download period (`oasis.retention_days`, AGB § 13) runs from the end of the trial, the paid period or the gift,
 * whichever is last. Deletion goes through DeleteAccount, so images, oasis hand-over and automated checks are handled
 * exactly as when members delete their account themselves (Art. 17 GDPR).
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
    public function handle(DeleteAccount $deleteAccount): int
    {
        $enabled = config('oasis.retention.auto_delete_expired', false) || $this->option('force');
        $retentionDays = (int) config('oasis.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        // Candidates: members whose trial, paid period and gift all ended before the cutoff, and no running subscription.
        $candidateQuery = User::query()
            ->where('role', Role::Member)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $cutoff)
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where(fn ($running) => $running->where('status', 'active')->orWhere('paid_until', '>', $cutoff)))
            ->whereDoesntHave('giftMemberships', fn ($query) => $query->where('expires_at', '>', $cutoff));

        $count = $candidateQuery->count();

        if (! $enabled) {
            $this->info('Automatic account pruning is disabled (oasis.retention.auto_delete_expired is false).');
            $this->info("Found {$count} expired accounts past the {$retentionDays}-day retention period.");

            return self::SUCCESS;
        }

        $this->warn("Pruning {$count} expired accounts past {$retentionDays}-day retention window...");

        $deletedCount = 0;
        foreach ($candidateQuery->get() as $user) {
            try {
                $deleteAccount->handle($user);
                $deletedCount++;
            } catch (ValidationException) {
                // A membership was started meanwhile; the account stays.
            }
        }

        $this->info("Pruned {$deletedCount} expired accounts.");

        return self::SUCCESS;
    }
}
