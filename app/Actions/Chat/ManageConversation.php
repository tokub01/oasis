<?php

namespace App\Actions\Chat;

use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\ConversationMember;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Creates chat membership and applies owner-only membership/settings changes under a conversation lock. */
class ManageConversation
{
    /**
     * Require real, verified, chat-ready accounts; never accept merely an existing user ID.
     *
     * @param  list<int>  $ids
     */
    private function requireReady(array $ids): void
    {
        $ready = User::query()->whereIn('id', $ids)->whereNotNull('email_verified_at')
            ->whereIn('id', ChatIdentity::query()->select('user_id'))->count();
        if ($ready !== count($ids)) {
            throw ValidationException::withMessages(['member_ids' => 'Alle Mitglieder müssen den Chat eingerichtet haben.']);
        }
    }

    /** @param list<int> $ids */
    private function requireInvitable(User $actor, array $ids): void
    {
        $invitees = User::whereIn('id', $ids)->whereKeyNot($actor->id)->with('privacySetting')->get();
        PrivacySetting::preloadFollows($invitees, [$actor->id]);
        foreach ($invitees as $invitee) {
            abort_unless($invitee->privacy()->allows('chat_invites_audience', $actor), 403,
                'Diese Person erlaubt dir keine Chat-Einladungen.');
        }
    }

    /**
     * The caller becomes owner; initial messages remain blocked until key distribution is activated.
     *
     * @param  array{type: string, title?: string|null, member_ids: list<int|string>, lifetime_days: int}  $input
     */
    public function create(User $owner, array $input): Conversation
    {
        $ids = array_map('intval', $input['member_ids']);
        if (in_array($owner->id, $ids, true) || ($input['type'] === 'direct' && count($ids) !== 1)) {
            throw ValidationException::withMessages(['member_ids' => 'Bitte wähle die anderen Mitglieder passend zur Chat-Art aus.']);
        }
        $allIds = [...$ids, $owner->id];
        if (DB::table('blocks')->whereIn('user_id', $allIds)->whereIn('blocked_user_id', $allIds)->exists()) {
            throw ValidationException::withMessages(['member_ids' => 'Mit blockierten Personen kann kein Chat gestartet werden.']);
        }
        $ids[] = $owner->id;

        return DB::transaction(function () use ($owner, $input, $ids) {
            ConversationKeys::lockUsers($ids);
            $this->requireReady($ids);
            if (DB::table('blocks')->whereIn('user_id', $ids)->whereIn('blocked_user_id', $ids)->exists()) {
                throw ValidationException::withMessages(['member_ids' => 'Mit blockierten Personen kann kein Chat gestartet werden.']);
            }
            $this->requireInvitable($owner, $ids);
            $conversation = Conversation::create(['type' => $input['type'], 'title' => $input['title'] ?? null,
                'created_by' => $owner->id, 'lifetime_days' => $input['lifetime_days'],
                'key_version' => 1, 'rotation_required' => true]);
            foreach ($ids as $id) {
                ConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $id,
                    'role' => $id === $owner->id ? 'owner' : 'member', 'joined_at' => now()]);
            }

            return $conversation;
        }, 3);
    }

    /**
     * Changing expiry affects future messages only; existing signed expiry is immutable.
     *
     * @param  array<string, mixed>  $input
     */
    public function settings(Conversation $conversation, User $owner, array $input): Conversation
    {
        return DB::transaction(function () use ($conversation, $owner, $input) {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $conversation->requireMember($owner, true);
            $conversation->update($input);

            return $conversation;
        }, 3);
    }

    /** Every membership change starts a new version, including rejoining a previous group. */
    public function membership(Conversation $conversation, User $owner, int $userId, bool $add): Conversation
    {
        return DB::transaction(function () use ($conversation, $owner, $userId, $add) {
            ConversationKeys::lockUsers([$owner->id, $userId]);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $conversation->requireMember($owner, true);
            abort_unless($conversation->type === 'group', 422, 'Direkte Chats haben feste Mitglieder.');
            abort_if($userId === (int) $conversation->created_by, 422, 'Der Besitzer bleibt Mitglied.');
            $member = $conversation->memberships()->where('user_id', $userId)->first();
            if ($add) {
                $activeMemberIds = $conversation->activeMembers()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                $checkIds = array_unique([...$activeMemberIds, $userId]);
                abort_if(
                    DB::table('blocks')->whereIn('user_id', $checkIds)->whereIn('blocked_user_id', $checkIds)->exists(),
                    422,
                    'Mit blockierten Personen kann kein Chat geführt werden.'
                );
                $this->requireReady([$userId]);
                $this->requireInvitable($owner, [$userId]);
                abort_if($member && $member->left_at === null, 422, 'Dieses Konto ist bereits Mitglied.');
                abort_if($conversation->activeMembers()->count() >= 50, 422, 'Ein Chat hat höchstens 50 Mitglieder.');
                ConversationKeyGrant::query()->where('conversation_id', $conversation->id)
                    ->where('recipient_id', $userId)->delete();
                ConversationMember::updateOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId],
                    ['role' => 'member', 'joined_at' => now(), 'left_at' => null]);
            } else {
                abort_unless($member && $member->left_at === null, 404);
                $member->update(['left_at' => now()]);
            }
            ConversationKeys::invalidate($conversation);

            return $conversation;
        }, 3);
    }
}
