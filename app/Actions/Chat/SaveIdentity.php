<?php

namespace App\Actions\Chat;

use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Saves only the caller's encrypted identity; public-key replacements invalidate recipient grants and keys. */
class SaveIdentity
{
    /**
     * Relocking the same keys preserves trust timestamps and existing conversation keys.
     *
     * @param array{locked_identity: array<string, mixed>, public_keys: array<string, string>} $input
     */
    public function handle(User $user, array $input): ChatIdentity
    {
        return DB::transaction(function () use ($user, $input) {
            ConversationKeys::lockUsers([$user->id]);
            $identity = ChatIdentity::query()->where('user_id', $user->id)->first();
            $changed = $identity && ($identity->public_keys['encryption'] !== $input['public_keys']['encryption']
                || $identity->public_keys['signing'] !== $input['public_keys']['signing']);
            if ($changed) {
                $conversations = Conversation::query()->whereHas('activeMembers',
                    fn ($query) => $query->where('user_id', $user->id))->orderBy('id')->lockForUpdate()->get();
                ConversationKeyGrant::query()->where('recipient_id', $user->id)->delete();
                foreach ($conversations as $conversation) {
                    ConversationKeys::invalidate($conversation);
                }
            }

            return ChatIdentity::updateOrCreate(['user_id' => $user->id], [
                'locked_identity' => $input['locked_identity'], 'public_keys' => $input['public_keys'],
                'key_changed_at' => $changed ? now() : $identity?->key_changed_at,
            ]);
        }, 3);
    }
}
