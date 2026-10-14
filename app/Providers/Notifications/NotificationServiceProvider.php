<?php

namespace App\Providers\Notifications;

use App\Console\Commands\Notifications\PruneNotifications;
use App\Console\Commands\Notifications\SendNotificationDigest;
use App\Events\Network\Followed;
use App\Events\Network\PostPublished;
use App\Listeners\Notifications\OnFollowed;
use App\Listeners\Notifications\OnPostPublished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Cache-safe event, command and scheduling registration for the notification package. */
class NotificationServiceProvider extends ServiceProvider
{
    /** Keep commands available both in web and console application boots. */
    public function register(): void
    {
        $this->commands([SendNotificationDigest::class, PruneNotifications::class]);
    }

    /** Explicit process methods avoid duplicate automatic handle/__invoke discovery. */
    public function boot(): void
    {
        Event::listen(PostPublished::class, OnPostPublished::class.'@process');
        Event::listen(Followed::class, OnFollowed::class.'@process');
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('oasis:notification-digest')->timezone('Europe/Berlin')->dailyAt('18:00')->withoutOverlapping();
            $schedule->command('oasis:prune-notifications')->daily()->withoutOverlapping();
        });
    }
}
