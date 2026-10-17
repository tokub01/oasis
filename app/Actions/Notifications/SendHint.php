<?php

namespace App\Actions\Notifications;

use App\Enums\Notifications\NoticeKind;
use App\Models\Notifications\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NetworkHint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/** Single delivery boundary: preferences, safety (blocks, mutes, restrictions, hidden words), visibility and idempotency. */
class SendHint
{
    /** Rechecked by the inbox and digest so later blocks/removals do not expose old excerpts. */
    public function allowed(User $recipient, User $actor, ?Post $post = null, ?NoticeKind $kind = null): bool
    {
        return ! $recipient->is($actor)
            && ! $recipient->isBlockedWith($actor)
            && ! in_array($actor->id, $recipient->mutedUserIds(), true)
            && ! in_array($actor->id, $recipient->restrictedUserIds(), true)
            && ($kind !== NoticeKind::Mention || $recipient->privacy()->allows('mentions_audience', $actor))
            && ($post === null || ($post->isVisibleTo($recipient) && ! $recipient->privacy()->hides($post->body)));
    }

    /**
     * Deliver at most once per event and recipient, even if two listeners retry concurrently.
     *
     * @param  array<string, int>  $ids  Additional resource IDs (oasis_id or invitation_id).
     */
    public function handle(User $recipient, User $actor, NoticeKind $kind, string $eventKey, ?Post $post = null, array $ids = []): void
    {
        if (! $this->allowed($recipient, $actor, $post, $kind)) {
            return;
        }
        DB::transaction(function () use ($recipient, $actor, $kind, $eventKey, $post, $ids): void {
            // Serialize deliveries for this account; the deterministic UUID additionally enforces uniqueness.
            User::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            $preference = NotificationPreference::where('user_id', $recipient->id)->first();
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'oasis:hint:'.$recipient->id.':'.$eventKey)->toString();
            if (($preference !== null && ! $preference->enabled($kind)) || $recipient->notifications()->whereKey($id)->exists()) {
                return;
            }
            $recipient->notify(new NetworkHint($id, [
                'kind' => $kind->value,
                'actor_id' => $actor->id,
                'post_id' => $post?->id,
                'excerpt' => $post === null ? '' : Str::substr($post->body, 0, 120),
            ] + $ids));
        });
    }
}
