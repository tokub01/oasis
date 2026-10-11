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

    /**
     * Replies to a Tropfen, oldest first (a conversation reads top-down).
     *
     * @return Collection<int, Post>
     */
    public function replies(Post $post, ?User $viewer): Collection
    {
        return $this->withCardData(Post::query()->where('reply_to_id', $post->id)->visibleTo($viewer), $viewer)
            ->orderBy('id')->limit(200)->get();
    }

    /**
     * @param  Builder<Post>  $query
     * @param  User|null  $reactor  account whose own reaction state is shown (defaults to the visibility viewer)
     */
    private function paginate(?User $viewer, Builder $query, ?User $reactor = null): CursorPaginator
    {
        return $this->withCardData($query->visibleTo($viewer), $reactor ?? $viewer)
            ->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
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
            ]));
    }
}
