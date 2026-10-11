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
