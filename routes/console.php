<?php

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

// Stage 10: re-queue automated content checks that never ran (e.g. queue worker down).
Schedule::command('oasis:moderation-sweep')->everyTenMinutes()->withoutOverlapping();
