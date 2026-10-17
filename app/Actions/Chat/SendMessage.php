<?php

namespace App\Actions\Chat;

use App\Events\Chat\MessageSent;
use App\Jobs\Chat\NotifyChatRecipients;
use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Chat\ChatBroadcast;
use App\Support\Chat\CryptoPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validates a member's signed opaque envelope and stores/relays it with its original expiry. */
class SendMessage
{
    /** Messages a sender may send in a direct chat that the other person has not accepted yet. */
    public const REQUEST_MESSAGE_LIMIT = 3;

    /**
     * Lock identity before conversation; a pending rotation or stale version cannot send ciphertext.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function handle(Conversation $conversation, User $sender, array $envelope): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $envelope) {
            ConversationKeys::lockUsers([$sender->id]);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $membership = $conversation->requireMember($sender);
            // Replying to a message request accepts it.
            if ($membership->accepted_at === null) {
                $membership->update(['accepted_at' => now()]);
            }
            // While the other person has not accepted a direct chat, only a few messages may go out (no spamming).
            if ($conversation->type === 'direct' && $conversation->activeMembers()->where('user_id', '!=', $sender->id)->whereNull('accepted_at')->exists()
                && Message::where('conversation_id', $conversation->id)->where('sender_id', $sender->id)->count() >= self::REQUEST_MESSAGE_LIMIT) {
                abort(429, 'Deine Nachrichten sind noch eine Anfrage. Du kannst weiter schreiben, sobald sie angenommen ist.');
            }
            abort_if($conversation->activeMembers()->whereIn('user_id', $sender->blockedUserIds(true))->exists(),
                403, 'Mit blockierten Personen können keine Nachrichten ausgetauscht werden.');
            abort_unless(! $conversation->rotation_required && $conversation->key_version === (int) $envelope['key_version'],
                409, 'Bitte warte auf den neuen Chat-Schlüssel.');
            $expiry = CarbonImmutable::parse($envelope['expires_at']);
            $expected = now()->addDays($conversation->lifetime_days);
            $identity = ChatIdentity::query()->where('user_id', $sender->id)->first();
            if ($envelope['conversation_id'] !== (string) $conversation->id
                || $envelope['sender_id'] !== (string) $sender->id || ! $expiry->isFuture()
                || abs($expiry->getTimestamp() - $expected->getTimestamp()) > 120
                || ! $identity || ! CryptoPayload::messageValid($envelope, $identity->public_keys)) {
                throw ValidationException::withMessages(['envelope' => 'Die signierte Nachricht oder ihre Ablaufzeit ist ungültig.']);
            }
            $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id,
                'key_version' => $conversation->key_version, 'envelope' => $envelope,
                'expires_at' => $expiry, 'created_at' => now()]);
            ChatBroadcast::afterCommit(new MessageSent($envelope));
            // The sender has seen everything up to their own message; others may get an e-mail/push if they opted in.
            $conversation->memberships()->where('user_id', $sender->id)->update(['last_read_at' => now()]);
            NotifyChatRecipients::dispatch($conversation->id, $sender->id)->afterCommit()->delay(NotifyChatRecipients::DELAY_SECONDS);

            return $message;
        }, 3);
    }
}
