<?php

namespace App\Actions\Chat;

use App\Events\Chat\KeyRotated;
use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\User;
use App\Support\Chat\ChatBroadcast;
use App\Support\Chat\CryptoPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Serializes conversation key changes and verifies complete owner-signed distribution batches. */
class ConversationKeys
{
    /**
     * Lock account rows in a stable order before conversation locks, including during identity replacement.
     *
     * @param  list<int|string>  $ids
     */
    public static function lockUsers(array $ids): void
    {
        User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    /** Start a fresh pending version after membership or public-key changes; caller holds the row lock. */
    public static function invalidate(Conversation $conversation): void
    {
        $conversation->increment('key_version');
        $conversation->update(['rotation_required' => true]);
        ChatBroadcast::afterCommit(new KeyRotated((string) $conversation->id, $conversation->key_version, true));
    }

    /**
     * Store all current-version grants atomically; no recipient can receive an incomplete new key set.
     *
     * @param  array{key_version: int, grants: list<array<string, mixed>>}  $input
     */
    public function distribute(Conversation $conversation, User $owner, array $input): Conversation
    {
        return DB::transaction(function () use ($conversation, $owner, $input) {
            self::lockUsers(array_values($conversation->activeMembers()->pluck('user_id')->map(fn ($id): int => (int) $id)->all()));
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $conversation->requireMember($owner, true);
            abort_unless($conversation->rotation_required && $conversation->key_version === (int) $input['key_version'],
                409, 'Der Schlüsselstand hat sich geändert.');
            $ids = $conversation->activeMembers()->pluck('user_id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $recipients = collect($input['grants'])->pluck('recipient_id')->sort()->values()->all();
            if ($recipients !== $ids) {
                throw ValidationException::withMessages(['grants' => 'Für jedes aktive Mitglied wird genau ein Schlüssel benötigt.']);
            }
            $identities = ChatIdentity::query()->whereIn('user_id', $ids)->get()->keyBy('user_id');
            foreach ($input['grants'] as $grant) {
                if ($grant['conversation_id'] !== (string) $conversation->id
                    || (int) $grant['key_version'] !== $conversation->key_version
                    || $grant['granter_id'] !== (string) $owner->id
                    || ! isset($identities[$grant['recipient_id']], $identities[$owner->id])
                    || ! CryptoPayload::grantValid($grant, $identities[$owner->id]->public_keys,
                        $identities[$grant['recipient_id']]->public_keys)) {
                    throw ValidationException::withMessages(['grants' => 'Das signierte Schlüsselpaket ist ungültig.']);
                }
            }
            // Pending batches can be replaced after an interrupted unlock, but activated grants are immutable.
            ConversationKeyGrant::query()->where('conversation_id', $conversation->id)
                ->where('key_version', $conversation->key_version)->delete();
            foreach ($input['grants'] as $grant) {
                ConversationKeyGrant::create(['conversation_id' => $conversation->id,
                    'key_version' => $conversation->key_version, 'recipient_id' => $grant['recipient_id'],
                    'granter_id' => $owner->id, 'grant' => $grant]);
            }

            return $conversation;
        }, 3);
    }

    /** Activate only a fully distributed current key; concurrent membership changes force a retry. */
    public function activate(Conversation $conversation, User $owner, int $version): Conversation
    {
        return DB::transaction(function () use ($conversation, $owner, $version) {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $conversation->requireMember($owner, true);
            abort_unless($version === $conversation->key_version, 409, 'Der Schlüsselstand hat sich geändert.');
            $ids = $conversation->activeMembers()->pluck('user_id')->all();
            $count = ConversationKeyGrant::query()->where('conversation_id', $conversation->id)
                ->where('key_version', $version)->whereIn('recipient_id', $ids)->count();
            abort_unless($count === count($ids), 409, 'Die Schlüsselverteilung ist noch nicht vollständig.');
            $conversation->update(['rotation_required' => false]);
            ChatBroadcast::afterCommit(new KeyRotated((string) $conversation->id, $version, false));

            return $conversation;
        }, 3);
    }
}
