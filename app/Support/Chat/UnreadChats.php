<?php

namespace App\Support\Chat;

use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Which chats have messages a member has not seen (docs/oasis-chat.md, "Benachrichtigungen"). Metadata only: a chat
 * is unread when it holds an unexpired message from someone else, not blocked, newer than the member's last visit
 * (`last_read_at`, or the join for a chat never opened). Message requests (not accepted yet) never count. The server
 * never needs the content for this.
 */
final class UnreadChats
{
    /** @return list<int> conversation ids, newest activity first is not guaranteed */
    public static function conversationIds(User $user): array
    {
        return array_values(array_map('intval', self::memberships($user)->pluck('conversation_id')->all()));
    }

    public static function count(User $user): int
    {
        return self::memberships($user)->count();
    }

    /** Open message requests: shown as a quiet "Anfragen" entry, never as a count, mail or push. */
    public static function requestCount(User $user): int
    {
        return ConversationMember::query()->where('user_id', $user->id)->whereNull('left_at')->whereNull('accepted_at')->count();
    }

    /** Whether this membership has something unread right now (used before an e-mail or push goes out). */
    public static function isUnread(ConversationMember $member): bool
    {
        return ConversationMember::whereKey($member->id)->tap(fn (Builder $query) => self::withUnreadMessages($query, $member->user))->exists();
    }

    /** @return Builder<ConversationMember> */
    private static function memberships(User $user): Builder
    {
        return ConversationMember::query()->where('user_id', $user->id)->whereNull('left_at')->whereNotNull('accepted_at')
            ->tap(fn (Builder $query) => self::withUnreadMessages($query, $user));
    }

    /** @param  Builder<ConversationMember>  $query */
    private static function withUnreadMessages(Builder $query, User $user): void
    {
        $blocked = $user->blockedUserIds(true);
        $query->whereExists(fn (QueryBuilder $messages) => $messages->from('messages')
            ->whereColumn('messages.conversation_id', 'conversation_members.conversation_id')
            ->where('messages.sender_id', '!=', $user->id)
            ->when($blocked !== [], fn (QueryBuilder $q) => $q->whereNotIn('messages.sender_id', $blocked))
            ->where('messages.expires_at', '>', now())
            ->whereRaw('messages.created_at > COALESCE(conversation_members.last_read_at, conversation_members.joined_at)'));
    }
}
