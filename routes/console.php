<?php

use App\Models\SystemHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes data whose retention period from config('oasis.privacy') has passed (privacy policy promises).
// Needs the scheduler in production: `* * * * * php artisan schedule:run`.
Schedule::command('model:prune')->daily();

// Stage 1: Send reminder mail 2 days before trial ends
Schedule::command('oasis:trial-reminders')->dailyAt('09:00');

// Stage 1: Retention pruning for expired accounts past retention_days (behind config switch)
Schedule::command('oasis:prune-expired-accounts')->dailyAt('03:00');

// Reconcile missed payment callbacks without overlapping ledger updates.
Schedule::command('oasis:mollie-sync')->everyTenMinutes()->withoutOverlapping();

// Operations (docs/oasis-operations.md): heartbeat proving the scheduler runs, encrypted backup, weekly restore test
// and the health check that mails the operator about problems.
Schedule::call(fn () => SystemHeartbeat::success('scheduler'))->everyMinute()->name('scheduler-heartbeat');
Schedule::command('oasis:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('oasis:backup-verify')->weeklyOn(0, '04:30')->withoutOverlapping();
Schedule::command('oasis:health-check')->everyFifteenMinutes()->withoutOverlapping();

// Bot protection: refresh the list of throwaway e-mail domains (docs/oasis-bot-protection.md).
Schedule::command('oasis:disposable-domains-update')->monthlyOn(1, '05:00');

// Stage 10: re-queue automated content checks that never ran (e.g. queue worker down).
Schedule::command('oasis:moderation-sweep')->everyTenMinutes()->withoutOverlapping();
