<?php

namespace App\Queries\Network;

use App\Models\Follow;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Deine Zahlen": statistics a member sees about their own Tropfen and Karawane – and nobody else does.
 * Only counts; never who reacted or who stopped following.
 */
class MemberStats
{
    /** Weeks shown in the weekly charts. */
    public const WEEKS = 12;

    /**
     * Totals over the whole account lifetime.
     *
     * @return array{posts: int, reactions: int, replies: int, carries: int, followers: int, following: int}
     */
    public function totals(User $member): array
    {
        $ownPosts = Post::where('user_id', $member->id)->select('id');

        return [
            'posts' => Post::where('user_id', $member->id)->count(),
            'reactions' => Reaction::whereIn('post_id', $ownPosts)->count(),
            'replies' => Post::whereIn('reply_to_id', $ownPosts)->where('user_id', '!=', $member->id)->count(),
            'carries' => Post::whereIn('carried_post_id', $ownPosts)->where('user_id', '!=', $member->id)->count(),
            'followers' => $member->followers()->count(),
            'following' => $member->following()->count(),
        ];
    }

    /**
     * Numbers of the last days for the Quelle side column: new followers and reactions received.
     *
     * @return array{followers: int, reactions: int, karawane: int}
     */
    public function lastDays(User $member, int $days): array
    {
        $since = now()->subDays($days);

        return [
            'followers' => $member->followers()->where('created_at', '>=', $since)->count(),
            'reactions' => Reaction::whereIn('post_id', Post::where('user_id', $member->id)->select('id'))
                ->where('created_at', '>=', $since)->count(),
            'karawane' => $member->followers()->count(),
        ];
    }

    /**
     * Per calendar week (Monday start, oldest first): new followers, Tropfen written and reactions received.
     *
     * @return Collection<int, array{week: CarbonImmutable, followers: int, posts: int, reactions: int}>
     */
    public function weekly(User $member, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfWeek()->subWeeks(self::WEEKS - 1);
        $ownPosts = Post::where('user_id', $member->id)->select('id');

        $followers = $this->weeklyCounts(Follow::where('followable_type', $member->getMorphClass())
            ->where('followable_id', $member->id)->where('created_at', '>=', $start)->pluck('created_at'));
        $posts = $this->weeklyCounts(Post::where('user_id', $member->id)->where('created_at', '>=', $start)->pluck('created_at'));
        $reactions = $this->weeklyCounts(Reaction::whereIn('post_id', $ownPosts)->where('created_at', '>=', $start)->pluck('created_at'));

        return collect(range(0, self::WEEKS - 1))->map(function (int $offset) use ($start, $followers, $posts, $reactions) {
            $week = $start->addWeeks($offset);
            $key = $week->format('o-W');

            return ['week' => $week, 'followers' => $followers[$key] ?? 0, 'posts' => $posts[$key] ?? 0, 'reactions' => $reactions[$key] ?? 0];
        });
    }

    /**
     * The member's Tropfen of the last 30 days with the most reactions (at most 5).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Post>
     */
    public function topPosts(User $member): \Illuminate\Database\Eloquent\Collection
    {
        return Post::where('user_id', $member->id)->where('created_at', '>=', now()->subDays(30))
            ->withCount(['reactions', 'replies'])->orderByDesc('reactions_count')->orderByDesc('id')->limit(5)->get();
    }

    /**
     * Group timestamps by ISO week ("2026-41"); counted in PHP so the query works on every database driver.
     *
     * @param  Collection<int, mixed>  $timestamps
     * @return array<string, int>
     */
    private function weeklyCounts(Collection $timestamps): array
    {
        return $timestamps->countBy(fn ($time) => CarbonImmutable::parse($time)->format('o-W'))->all();
    }
}
