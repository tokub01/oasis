<?php

namespace App\Support\Chat;

use App\Models\ChatIdentity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Whom a member may start a chat with, for the "Schreiben" buttons on profiles and oasis member lists
 * (docs/oasis-chat.md). The same rules as the chat's own user search (PublicKeysController::users): the other person
 * has set up the chat, nobody blocked anybody, and their setting "Wer darf dir schreiben?" allows the writer. A
 * read-only or locked account writes to nobody. The chat itself checks all of this again when the chat is created.
 */
final class ChatReach
{
    /**
     * Ids of the people in $targets that $writer may write to.
     *
     * @param  Collection<int, User>  $targets
     * @return list<int>
     */
    public static function writableIds(?User $writer, Collection $targets): array
    {
        if ($writer === null || $targets->isEmpty() || ! $writer->canParticipate()) {
            return [];
        }
        $blocked = $writer->blockedUserIds();
        $ready = ChatIdentity::whereIn('user_id', $targets->map(fn (User $target) => $target->id)->all())
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        return array_values($targets
            ->filter(fn (User $target) => $target->id !== $writer->id && in_array($target->id, $ready, true)
                && ! in_array($target->id, $blocked, true) && $target->privacy()->allows('chat_invites_audience', $writer))
            ->map(fn (User $target) => $target->id)->all());
    }

    /** Link that opens a new chat with $target preselected (resources/js/chat/ui/route.js, "#neu/{username}"). */
    public static function startUrl(User $target): string
    {
        return route('app.chat').'#neu/'.$target->username;
    }
}
