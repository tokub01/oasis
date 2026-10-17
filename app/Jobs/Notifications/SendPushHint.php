<?php

namespace App\Jobs\Notifications;

use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\WebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Tell a member's browsers that there is a new hint (docs/oasis-pwa.md). The push carries no names and no texts, only
 * "Neuer Hinweis" and a link: what happened is read in the app. Skipped during quiet hours and a pause (the hint
 * waits in the app) and at most once per COOLDOWN, so a busy evening does not turn into a stream of pushes.
 */
class SendPushHint implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const COOLDOWN_SECONDS = 300;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $push = WebPush::fromConfig();
        $user = User::find($this->userId);
        if ($push === null || $user === null || $user->pushSubscriptions()->doesntExist() || NotificationPreference::quietFor($user)) {
            return;
        }
        if (! Cache::add('oasis:push-hint:'.$user->id, true, self::COOLDOWN_SECONDS)) {
            return;
        }
        foreach ($user->pushSubscriptions as $subscription) {
            $push->send($subscription, ['title' => 'Oasis', 'body' => 'Du hast einen neuen Hinweis.', 'url' => '/app/hinweise', 'tag' => 'oasis-hinweise']);
        }
    }
}
