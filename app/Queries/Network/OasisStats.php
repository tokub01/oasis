<?php

namespace App\Queries\Network;

use App\Enums\Network\OasisRole;
use App\Models\Oasis;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Statistics for the hosts of one oasis: sums only, never lists of people – hosts see how their space is doing, not
 * who reads what.
 */
class OasisStats
{
    public const WEEKS = 12;

    /** @return array{members: int, hosts: int, posts: int, posts_30_days: int, active_authors_30_days: int, removed: int} */
    public function totals(Oasis $oasis): array
    {
        $since = now()->subDays(30);

        return [
            'members' => $oasis->members()->count(),
            'hosts' => $oasis->members()->wherePivot('role', OasisRole::Host->value)->count(),
            'posts' => $oasis->posts()->count(),
            'posts_30_days' => $oasis->posts()->where('created_at', '>=', $since)->count(),
            'active_authors_30_days' => $oasis->posts()->where('created_at', '>=', $since)->distinct()->count('user_id'),
            'removed' => $oasis->posts()->whereNotNull('removed_from_oasis_at')->count(),
        ];
    }

    /**
     * Per calendar week (oldest first): members who joined and Tropfen written.
     *
     * @return Collection<int, array{week: CarbonImmutable, joined: int, posts: int}>
     */
    public function weekly(Oasis $oasis, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfWeek()->subWeeks(self::WEEKS - 1);
        $week = fn ($time) => CarbonImmutable::parse($time)->format('o-W');

        $joined = $oasis->members()->wherePivot('joined_at', '>=', $start)->pluck('oasis_members.joined_at')->countBy($week)->all();
        $posts = Post::where('oasis_id', $oasis->id)->where('created_at', '>=', $start)->pluck('created_at')->countBy($week)->all();

        return collect(range(0, self::WEEKS - 1))->map(function (int $offset) use ($start, $joined, $posts) {
            $current = $start->addWeeks($offset);
            $key = $current->format('o-W');

            return ['week' => $current, 'joined' => $joined[$key] ?? 0, 'posts' => $posts[$key] ?? 0];
        });
    }
}
