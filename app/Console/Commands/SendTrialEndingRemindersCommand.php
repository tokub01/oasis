<?php

namespace App\Console\Commands;

use App\Mail\Membership\TrialEndingReminderMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Scheduled command to send reminder emails 2 days before the free trial ends.
 * Idempotent: checks and records trial_reminder_sent_at.
 */
class SendTrialEndingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oasis:trial-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails to members whose trial ends in 2 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for trial accounts ending within 2 days...');

        // Window: trial ends between now and 48 hours (2 days) from now
        $threshold = now()->addDays(2)->endOfDay();

        $users = User::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', $threshold)
            ->whereNull('trial_reminder_sent_at')
            ->get();

        $sentCount = 0;

        foreach ($users as $user) {
            // Skip users who already have an active subscription or gift membership
            if ($user->hasActiveMembership() || $user->hasActiveGiftMembership()) {
                continue;
            }

            Mail::to($user->email)->send(new TrialEndingReminderMail($user));

            $user->update([
                'trial_reminder_sent_at' => now(),
            ]);

            $sentCount++;
        }

        $this->info("Sent {$sentCount} trial ending reminder emails.");

        return self::SUCCESS;
    }
}
