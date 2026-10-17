<?php

namespace App\Support\Notifications;

use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Enums\Privacy\Audience;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

/** Resolves IDs only at read time and hides deleted, blocked, muted or unreadable hints. */
class HintPresentation
{
    /** Resolve one hint freshly; callers with a list should use forMany to avoid per-hint queries.
     * @return array{label: string, url: string, excerpt: string}|null
     */
    public function forUser(DatabaseNotification $hint, User $viewer): ?array
    {
        return $this->forMany([$hint], $viewer)[(string) $hint->id] ?? null;
    }

    /**
     * Batch referenced resources and the mention audience circle, then recheck every hint's visibility and safety.
     * Nothing is cached between calls, so newly removed content remains hidden.
     *
     * @param  iterable<DatabaseNotification>  $hints
     * @return array<string, array{label: string, url: string, excerpt: string}> Visible items keyed by hint UUID.
     */
    public function forMany(iterable $hints, User $viewer): array
    {
        // Consume iterable items: a paginator's toArray also contains pagination metadata.
        $hints = collect([...$hints]);
        $actorIds = $hints->map(fn ($hint) => (int) ($hint->data['actor_id'] ?? 0))->filter()->unique()->all();
        $postIds = $hints->map(fn ($hint) => (int) ($hint->data['post_id'] ?? 0))->filter()->unique()->all();
        $oasisIds = $hints->map(fn ($hint) => (int) ($hint->data['oasis_id'] ?? 0))->filter()->unique()->all();
        $actors = $actorIds === [] ? collect() : User::whereKey($actorIds)->get()->keyBy('id');
        $posts = $postIds === [] ? collect() : Post::whereKey($postIds)->get()->keyBy('id');
        $oases = $oasisIds === [] ? collect() : Oasis::whereKey($oasisIds)->get()->keyBy('id');
        $recipient = clone $viewer;
        if ($actorIds !== [] && $recipient->privacy()->mentions_audience === Audience::Following) {
            PrivacySetting::preloadFollows(new Collection([$recipient]), array_values($actorIds));
        }
        $items = [];
        foreach ($hints as $hint) {
            $data = $hint->data;
            $item = $this->present($hint, $recipient, $actors->get($data['actor_id'] ?? 0),
                $posts->get($data['post_id'] ?? 0), $oases->get($data['oasis_id'] ?? 0));
            if ($item !== null) {
                $items[(string) $hint->id] = $item;
            }
        }

        return $items;
    }

    /** @return array{label: string, url: string, excerpt: string}|null */
    private function present(DatabaseNotification $hint, User $viewer, ?User $actor, ?Post $post, ?Oasis $oasis): ?array
    {
        $data = $hint->data;
        $kind = NoticeKind::tryFrom($data['kind'] ?? '');
        if ($kind === null || $actor === null || (isset($data['post_id']) && $post === null)
            || ! app(SendHint::class)->allowed($viewer, $actor, $post, $kind)) {
            return null;
        }
        $url = route('oasis.profil', $actor->username);
        if ($post !== null) {
            $url = route('network.posts.show', $post);
        }
        if ($kind === NoticeKind::FriendRequest) {
            $url = route('network.friends');
        }
        if ($kind === NoticeKind::Host) {
            if ($oasis === null || ! $oasis->isVisibleTo($viewer)) {
                return null;
            }
            $url = route('network.oases.show', $oasis);
        }

        return ['label' => $kind->label().' · '.$actor->display_name, 'url' => $url,
            'excerpt' => $post === null ? '' : ($data['excerpt'] ?? '')];
    }
}
