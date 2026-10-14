<?php

namespace App\Console\Commands\Notifications;

use App\Mail\Notifications\DailyDigest;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\HintPresentation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Daily, idempotent opt-in email digest, scheduled at 18:00 Europe/Berlin. */
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
                $user = User::find($preference->user_id);
                if ($user === null || ! $user->hasVerifiedEmail()) {
                    return;
                }
                $since = $preference->last_digest_at ?? $cutoff->copy()->subDay();
                $hints = $user->unreadNotifications()->where('created_at', '>', $since)
                    ->where('created_at', '<=', $cutoff)->get();
                $count = count($presentation->forMany($hints, $user));
                if ($count > 0) {
                    Mail::to($user->email)->send(new DailyDigest($count));
                }
                // Empty windows are checkpointed too; reading hints is never a side effect of email.
                $preference->update(['last_digest_at' => $cutoff]);
            });
        }
        $this->info('Tägliche Hinweis-Zusammenfassungen verarbeitet.');

        return self::SUCCESS;
    }
}
