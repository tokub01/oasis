<?php

namespace App\Actions\Chat;

use App\Events\Chat\MessageSent;
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
            $conversation->requireMember($sender);
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

            return $message;
        }, 3);
    }
}
