<?php

namespace App\Console\Commands\Notifications;

use App\Mail\Notifications\DailyDigest;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\HintPresentation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Daily, idempotent opt-in email digest, run hourly from 18:00 to 23:00 Europe/Berlin: each member gets it once, in the
 * first run outside their quiet hours or pause (NotificationPreference::isQuiet()).
 */
class SendNotificationDigest extends Command
{
    protected $signature = 'oasis:notification-digest';

    protected $description = 'Send opted-in members a quiet daily notification summary';

    /** Lock and checkpoint each preference only after synchronous mail succeeds; failures can be retried. */
    public function handle(HintPresentation $presentation): int
    {
        $cutoff = now();
        foreach (NotificationPreference::where('digest_enabled', true)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $cutoff, $presentation): void {
                $preference = NotificationPreference::whereKey($id)->lockForUpdate()->first();
                if ($preference === null || ! $preference->digest_enabled
                    || $preference->last_digest_at?->copy()->timezone('Europe/Berlin')->isSameDay($cutoff->copy()->timezone('Europe/Berlin'))) {
                    return;
                }
                // Quiet hours or pause: no mail now and no checkpoint, so the next summary still covers these hints.
                if ($preference->isQuiet($cutoff)) {
                    return;
                }
                $user = User::find($preference->user_id);
                if ($user === null || ! $user->hasVerifiedEmail()) {
                    return;
                }
                // Without an earlier summary: the last week, so a pause of up to seven days loses nothing.
                $since = $preference->last_digest_at ?? $cutoff->copy()->subWeek();
                $hints = $user->unreadNotifications()->where('created_at', '>', $since)
                    ->where('created_at', '<=', $cutoff)->get();
                $count = count($presentation->forMany($hints, $user));
                if ($count > 0) {
                    Mail::to($user->email)->send(new DailyDigest($count, $user));
                }
                // Empty windows are checkpointed too; reading hints is never a side effect of email.
                $preference->update(['last_digest_at' => $cutoff]);
            });
        }
        $this->info('Tägliche Hinweis-Zusammenfassungen verarbeitet.');

        return self::SUCCESS;
    }
}
