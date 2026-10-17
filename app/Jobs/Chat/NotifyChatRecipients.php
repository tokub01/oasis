<?php

namespace App\Jobs\Chat;

use App\Mail\Chat\NewChatMessage;
use App\Models\ConversationMember;
use App\Models\Notifications\NotificationPreference;
use App\Support\Chat\UnreadChats;
use App\Support\Notifications\WebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail and push about a new chat message, for members who switched it on (privacy settings `chat_email`,
 * `chat_push`, both off by default). Dispatched with DELAY_SECONDS, so nothing goes out when the chat was read
 * meanwhile. One notice per chat until it is read again (`notified_at`), none during quiet hours or a pause, none
 * from blocked people. Contains neither the text (end-to-end encrypted) nor a name.
 */
class NotifyChatRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const DELAY_SECONDS = 120;

    public function __construct(public readonly int $conversationId, public readonly int $senderId) {}

    public function handle(): void
    {
        $members = ConversationMember::with('user')->where('conversation_id', $this->conversationId)
            ->whereNull('left_at')->whereNotNull('accepted_at')->where('user_id', '!=', $this->senderId)->get();
        foreach ($members as $member) {
            $user = $member->user;
            $privacy = $user->privacy();
            if ((! $privacy->chat_email && ! $privacy->chat_push)
                || in_array($this->senderId, $user->blockedUserIds(true), true)
                || ($member->notified_at !== null && ($member->last_read_at === null || $member->notified_at > $member->last_read_at))
                || NotificationPreference::quietFor($user)
                || ! UnreadChats::isUnread($member)) {
                continue;
            }
            $member->forceFill(['notified_at' => now()])->save();
            if ($privacy->chat_email) {
                Mail::to($user->email)->send(new NewChatMessage($user));
            }
            $push = WebPush::fromConfig();
            if ($privacy->chat_push && $push !== null) {
                foreach ($user->pushSubscriptions as $subscription) {
                    $push->send($subscription, ['title' => 'Oasis', 'body' => 'Du hast eine neue Chat-Nachricht.', 'url' => '/app/nachrichten', 'tag' => 'oasis-chat']);
                }
            }
        }
    }
}
