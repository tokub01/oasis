<?php

namespace App\Queries\Network;

use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chronological lists of Tropfen – no ranking, no injected recommendations ("Ein Feed, der aufhört").
 * Every list applies {@see Post::scopeVisibleTo()} and loads what the card partial needs in a fixed number of
 * queries. Counts are loaded for every card but shown only to the author (PostPolicy::viewStats).
 */
class PostFeed
{
    public const PER_PAGE = 20;

    /** "Quelle": own Tropfen plus those of followed people and oases; replies stay on the Tropfen page. */
    public function source(User $viewer): CursorPaginator
    {
        $people = $viewer->following()->where('followable_type', (new User)->getMorphClass())->select('followable_id');
        $oases = $viewer->following()->where('followable_type', (new Oasis)->getMorphClass())->select('followable_id');

        return $this->paginate($viewer, Post::query()
            ->whereNull('reply_to_id')
            ->where(fn (Builder $query) => $query->where('user_id', $viewer->id)
                ->orWhereIn('user_id', $people)
                ->orWhereIn('oasis_id', $oases)));
    }

    /** Tropfen of one person (profile page), without replies. */
    public function byAuthor(User $author, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('user_id', $author->id)->whereNull('reply_to_id'));
    }

    /** Tropfen in one oasis, without replies. */
    public function inOasis(Oasis $oasis, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('oasis_id', $oasis->id)->whereNull('reply_to_id'));
    }

    /** Public Tropfen of public profiles for "Entdecken" (guests and members). */
    public function discover(?User $viewer): CursorPaginator
    {
        return $this->paginate(null, Post::query()->whereNull('reply_to_id')
            ->whereHas('author.profile', fn (Builder $profile) => $profile->where('visibility', 'public')), $viewer);
    }

    /** Search: Tropfen (also replies) whose text contains the term, newest first. LIKE wildcards are escaped. */
    public function matching(string $term, ?User $viewer): CursorPaginator
    {
        return $this->paginate($viewer, Post::query()->where('body', 'like', '%'.self::escapeLike($term).'%'));
    }

    /**
     * Tropfen carrying a #Thema, newest first. Matched as a whole word, case-insensitive, so "#garten" finds neither
     * "#gartenzaun" nor an e-mail-like "a#garten".
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
        $query = Post::query()->where('reply_to_id', $post->id)->visibleTo($viewer);

        return $this->withCardData($this->filterSafety($query, $viewer), $viewer)
            ->orderBy('id')->limit(200)->get();
    }

    /**
     * @param  Builder<Post>  $query
     * @param  User|null  $reactor  account whose own reaction state is shown (defaults to the visibility viewer)
     */
    private function paginate(?User $viewer, Builder $query, ?User $reactor = null): CursorPaginator
    {
        $actor = $reactor ?? $viewer;

        return $this->withCardData($this->filterSafety($query->visibleTo($viewer), $actor), $actor)
            ->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
    }

    /** Exclude posts from blocked users (bidirectional) and muted users (for this viewer). */
    private function filterSafety(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query;
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
        return $query
            ->with(['author.profile', 'oasis', 'images', 'carried.author.profile', 'carried.images', 'replyTo.author'])
            ->withCount(['reactions', 'replies'])
            ->when($viewer !== null, fn (Builder $mine) => $mine->withExists([
                'reactions as reacted' => fn (Builder $reaction) => $reaction->where('user_id', $viewer->id),
                'bookmarks as bookmarked' => fn (Builder $bookmark) => $bookmark->where('user_id', $viewer->id),
            ]));
    }
}
