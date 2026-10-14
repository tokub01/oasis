<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Events\Network\PostPublished;
use App\Models\User;
use App\Support\Network\Mentions;

/** One hint per Tropfen/recipient: reply takes precedence over carry, then mention. */
class OnPostPublished
{
    /** Process contexts first, then at most ten existing mentioned accounts in text order. */
    public function process(PostPublished $event): void
    {
        $post = $event->post;
        $sender = app(SendHint::class);
        $recipients = [];
        foreach ([$post->replyTo, $post->carried] as $index => $context) {
            if ($context !== null) {
                $recipients[$context->user_id] ??= [$context->author, $index === 0 ? NoticeKind::Reply : NoticeKind::Carry];
            }
        }
        $names = Mentions::usernames($post->body);
        $users = User::whereIn('username', $names)->get()->keyBy(fn (User $user) => strtolower($user->username));
        $mentioned = 0;
        foreach ($names as $name) {
            $user = $users->get($name);
            if ($user !== null && $sender->allowed($user, $post->author, $post)) {
                $recipients[$user->id] ??= [$user, NoticeKind::Mention];
                $mentioned++;
                if ($mentioned === 10) {
                    break;
                }
            }
        }
        foreach ($recipients as [$recipient, $kind]) {
            $sender->handle($recipient, $post->author, $kind, 'post:'.$post->id, $post);
        }
    }
}
