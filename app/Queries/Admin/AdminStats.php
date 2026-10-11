<?php

namespace App\Queries\Admin;

use App\Enums\Moderation\CaseStatus;
use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModerationCase;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use App\Queries\Membership\MembershipStats;
use App\Queries\Moderation\TransparencyQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Statistics module of the admin area: one place that combines membership (stage 1/5), network (stage 2), chat
 * (stage 3, metadata counts only) and moderation (stage 4) figures. Sums only – no lists of people, no content.
 */
class AdminStats
{
    public const WEEKS = 12;

    public function __construct(
        private readonly MembershipStats $membership,
        private readonly TransparencyQuery $moderation,
    ) {}

    /**
     * @return array{registered: int, verified: int, trials_active: int, paying: int, by_tier: array<string, int>,
     *     mrr: float, founding_used: int, founding_remaining: int, trials_started: int, trials_converted: int,
     *     trials_expired: int, cancellations: int, gifts_sold: int, gifts_redeemed: int}
     */
    public function members(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return [
            'registered' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'trials_active' => $this->membership->trialsActive(),
            'paying' => $this->membership->activeMembersCount(),
            'by_tier' => $this->membership->activeMembersByTier(),
            'mrr' => $this->membership->monthlyRecurringRevenue(),
            'founding_used' => $this->membership->foundingSeatsUsed(),
            'founding_remaining' => $this->membership->foundingSeatsRemaining(),
            'trials_started' => $this->membership->trialsStarted($from, $until),
            'trials_converted' => $this->membership->trialsConverted($from, $until),
            'trials_expired' => $this->membership->trialsExpired($from, $until),
            'cancellations' => $this->membership->cancellations($from, $until),
            'gifts_sold' => $this->membership->giftsSold($from, $until),
            'gifts_redeemed' => $this->membership->giftsRedeemed($from, $until),
        ];
    }

    /** @return array{posts: int, posts_period: int, active_authors: int, oases: int} */
    public function network(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $period = Post::where('created_at', '>=', $from)->where('created_at', '<', $until);

        return [
            'posts' => Post::count(),
            'posts_period' => (clone $period)->count(),
            'active_authors' => (clone $period)->distinct()->count('user_id'),
            'oases' => Oasis::count(),
        ];
    }

    /** Metadata counts only – the server cannot read chat content. @return array{identities: int, conversations: int, stored_messages: int, messages_period: int} */
    public function chat(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return [
            'identities' => ChatIdentity::count(),
            'conversations' => Conversation::count(),
            'stored_messages' => Message::where('expires_at', '>', now())->count(),
            'messages_period' => Message::where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
        ];
    }

    /** @return array{open: int, in_review: int, period: array<string, mixed>} */
    public function moderation(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return [
            'open' => ModerationCase::where('status', CaseStatus::Open->value)->count(),
            'in_review' => ModerationCase::where('status', CaseStatus::InReview->value)->count(),
            'period' => $this->moderation->forPeriod($from, $until),
        ];
    }

    /**
     * New accounts and Tropfen per calendar week, oldest first.
     *
     * @return Collection<int, array{week: CarbonImmutable, registered: int, posts: int}>
     */
    public function weekly(?CarbonImmutable $now = null): Collection
    {
        $start = ($now ?? CarbonImmutable::now())->startOfWeek()->subWeeks(self::WEEKS - 1);
        $byWeek = fn ($time) => CarbonImmutable::parse($time)->format('o-W');
        $registered = User::where('created_at', '>=', $start)->pluck('created_at')->countBy($byWeek)->all();
        $posts = Post::where('created_at', '>=', $start)->pluck('created_at')->countBy($byWeek)->all();

        return collect(range(0, self::WEEKS - 1))->map(function (int $offset) use ($start, $registered, $posts) {
            $week = $start->addWeeks($offset);
            $key = $week->format('o-W');

            return ['week' => $week, 'registered' => $registered[$key] ?? 0, 'posts' => $posts[$key] ?? 0];
        });
    }
}
