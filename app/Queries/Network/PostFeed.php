<?php

namespace App\Queries\Network;

use App\Enums\Network\PostKind;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\PrivacySetting;
use App\Models\User;
use App\Support\Network\Mentions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

/**
 * Chronological lists of Tropfen – no ranking, no injected recommendations ("Ein Feed, der aufhört").
 * Every list applies {@see Post::scopeVisibleTo()} and loads what the card partial needs in a fixed number of
 * queries. Counts are loaded for every card but shown only to the author (PostPolicy::viewStats).
 */
class PostFeed
{
    public const PER_PAGE = 20;

    /**
     * "Quelle": own Tropfen plus those of followed people and oases; replies from followed people are bundled under their origin Tropfen.
     *
     * @return CursorPaginator<int, Post>
     */
    public function source(User $viewer): CursorPaginator
    {
        $people = $viewer->following()->where('followable_type', (new User)->getMorphClass())->select('followable_id');
        $oases = $viewer->following()->where('followable_type', (new Oasis)->getMorphClass())->select('followable_id');

        $paginator = $this->paginate($viewer, Post::query()
            ->whereNull('reply_to_id')
            ->where(fn (Builder $query) => $query->where('user_id', $viewer->id)
                ->orWhereIn('user_id', $people)
                ->orWhereIn('oasis_id', $oases)));

        $this->loadConversationReplies($paginator->getCollection(), $viewer);

        return $paginator;
    }

    /**
     * Tropfen of one person (profile page), without replies.
     *
     * @return CursorPaginator<int, Post>
     */
    public function byAuthor(User $author, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('user_id', $author->id)->whereNull('reply_to_id'));
    }

    /**
     * Tropfen in one oasis, without replies.
     *
     * @return CursorPaginator<int, Post>
     */
    public function inOasis(Oasis $oasis, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('oasis_id', $oasis->id)->whereNull('reply_to_id'));
    }

    /**
     * Public Tropfen of public profiles for "Entdecken" (guests and members).
     *
     * @return CursorPaginator<int, Post>
     */
    public function discover(?User $viewer): CursorPaginator
    {
        return $this->paginate(null, Post::query()->whereNull('reply_to_id')
            ->whereHas('author.privacySetting', fn (Builder $privacy) => $privacy->where('profile_audience', 'everyone')), $viewer);
    }

    /**
     * Search: Tropfen (also replies) whose text contains the term, newest first. LIKE wildcards are escaped.
     *
     * @return CursorPaginator<int, Post>
     */
    public function matching(string $term, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('body', 'like', '%'.self::escapeLike($term).'%'));
    }

    /**
     * Tropfen carrying a #Thema, newest first. Matched as a whole word, case-insensitive, so "#garten" finds neither
     * "#gartenzaun" nor an e-mail-like "a#garten".
     *
     * @return CursorPaginator<int, Post>
     */
    public function tagged(string $tag, ?User $viewer): CursorPaginator
    {
        $pattern = '(^|[^[:alnum:]_])#'.preg_quote(mb_strtolower($tag), '/').'([^[:alnum:]_]|$)';

        return $this->paginate($viewer, Post::query()->whereRaw('LOWER(body) REGEXP ?', [$pattern]));
    }

    /**
     * Replies to a Tropfen, oldest first (a conversation reads top-down).
     *
     * @return Collection<int, Post>
     */
    public function replies(Post $post, ?User $viewer): Collection
    {
        $query = $this->withoutHiddenReplies(Post::query()->where('reply_to_id', $post->id)->visibleTo($viewer), $post->author, $viewer);

        $replies = $this->withCardData($this->filterSafety($query, $viewer), $viewer)
            ->orderBy('id')->limit(200)->get();
        Mentions::preloadPosts($replies);

        return $replies;
    }

    /**
     * @param  Builder<Post>  $query
     * @param  User|null  $reactor  account whose own reaction state is shown (defaults to the visibility viewer)
     * @return CursorPaginator<int, Post>
     */
    private function paginate(?User $viewer, Builder $query, ?User $reactor = null): CursorPaginator
    {
        $actor = $reactor ?? $viewer;

        $paginator = $this->withCardData($this->filterSafety($query->visibleTo($viewer), $actor), $actor)
            ->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
        Mentions::preloadPosts($paginator->getCollection());

        return $paginator;
    }

    /**
     * Replies the Tropfen's author hides – from restricted accounts or with one of their hidden words – stay visible
     * only to whoever wrote them (mirrors {@see User::hidesReplyFrom()}).
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    private function withoutHiddenReplies(Builder $query, User $owner, ?User $viewer): Builder
    {
        $restricted = $owner->restrictedUserIds();
        $words = $owner->privacy()->hiddenWords();
        if ($restricted === [] && $words === []) {
            return $query;
        }

        return $query->where(function (Builder $shown) use ($owner, $viewer, $restricted, $words): void {
            $shown->where(fn (Builder $clean) => $this->withoutWords($clean->when($restricted !== [],
                fn (Builder $people) => $people->whereNotIn('posts.user_id', $restricted)), $words))
                ->orWhere('posts.user_id', $owner->id)
                ->when($viewer !== null, fn (Builder $own) => $own->orWhere('posts.user_id', $viewer->id));
        });
    }

    /**
     * Texts without any of the words; LIKE follows the case-insensitive collation of the column.
     *
     * @param  Builder<Post>  $query
     * @param  list<string>  $words
     * @return Builder<Post>
     */
    private function withoutWords(Builder $query, array $words): Builder
    {
        foreach ($words as $word) {
            $query->where('posts.body', 'not like', '%'.self::escapeLike($word).'%');
        }

        return $query;
    }

    /**
     * Exclude posts from blocked users (bidirectional), muted users and – except the viewer's own – posts with one of
     * the viewer's hidden words.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    private function filterSafety(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query;
        }

        if (($words = $viewer->privacy()->hiddenWords()) !== []) {
            $query->where(fn (Builder $shown) => $shown->where('posts.user_id', $viewer->id)
                ->orWhere(fn (Builder $clean) => $this->withoutWords($clean, $words)));
        }

        if (! empty($blocked = $viewer->blockedUserIds())) {
            $query->whereNotIn('posts.user_id', $blocked);
        }

        if (! empty($muted = $viewer->mutedUserIds())) {
            $query->whereNotIn('posts.user_id', $muted);
        }

        return $query;
    }

    /** Escape `%`, `_` and `\` so user input is matched literally in LIKE. */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function withCardData(Builder $query, ?User $viewer): Builder
    {
        $viewer?->mutualFollowUserIds();

        return $query
            ->with(['author.profile', 'author.privacySetting', 'oasis', 'images', 'carried.author.profile', 'carried.author.privacySetting', 'carried.images', 'replyTo.author'])
            ->when($viewer !== null, fn (Builder $cards) => $cards->afterQuery(function (Collection $posts) use ($viewer): Collection {
                /** @var Collection<int, Post> $posts */
                /** @var Collection<int, User> $authors */
                $authors = new Collection;
                foreach ($posts as $post) {
                    $authors->push($post->author);
                    if ($post->carried !== null) {
                        $authors->push($post->carried->author);
                    }
                }
                PrivacySetting::preloadFollows($authors, [$viewer->id]);

                return $posts;
            }))
            ->withCount(['reactions', 'replies'])
            ->when($viewer !== null, fn (Builder $mine) => $mine->withExists([
                'reactions as reacted' => fn (Builder $reaction) => $reaction->where('user_id', $viewer->id),
                'bookmarks as bookmarked' => fn (Builder $bookmark) => $bookmark->where('user_id', $viewer->id),
            ]));
    }

    /**
     * Bundles conversation replies under their origin Tropfen for the Quelle.
     * Loads up to 2 replies from followed people (or replies to the viewer's own questions),
     * oldest first, and notes if more replies exist.
     *
     * @param  \Illuminate\Support\Collection<int, Post>  $posts
     */
    public function loadConversationReplies(\Illuminate\Support\Collection $posts, User $viewer): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        $postIds = $posts->pluck('id')->all();
        $followedUserIds = $viewer->following()
            ->where('followable_type', (new User)->getMorphClass())
            ->pluck('followable_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        // Build query for replies to these posts:
        $repliesQuery = Post::query()
            ->whereIn('reply_to_id', $postIds)
            ->visibleTo($viewer);

        $repliesQuery = $this->filterSafety($repliesQuery, $viewer);

        // Include replies from people the viewer follows (or the viewer themselves), or replies to the viewer's own questions
        $repliesQuery->where(function (Builder $q) use ($viewer, $followedUserIds): void {
            if (! empty($followedUserIds)) {
                $q->whereIn('posts.user_id', [...$followedUserIds, $viewer->id]);
            } else {
                $q->where('posts.user_id', $viewer->id);
            }
            $q->orWhere(function (Builder $questionReplies) use ($viewer): void {
                $questionReplies->whereHas('replyTo', function (Builder $parent) use ($viewer): void {
                    $parent->where('user_id', $viewer->id)->where('kind', PostKind::Question->value);
                });
            });
        });

        $origins = $posts->keyBy('id');
        $replies = $repliesQuery
            ->with(['author.profile', 'author.privacySetting'])
            ->orderBy('id', 'asc')
            ->get()
            ->reject(fn (Post $reply): bool => $reply->user_id !== $viewer->id
                && $origins->get($reply->reply_to_id)?->author?->hidesReplyFrom($reply) === true)
            ->groupBy('reply_to_id');

        foreach ($posts as $post) {
            $postReplies = $replies->get($post->id, collect());
            $post->setRelation('conversationReplies', $postReplies->take(2));
            $post->has_more_replies = ($post->replies_count ?? 0) > 2 || $postReplies->count() > 2;
        }
        Mentions::preloadPosts($posts);
    }
}
