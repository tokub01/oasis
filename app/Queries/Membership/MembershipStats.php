<?php

namespace App\Queries\Membership;

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only aggregate statistics queries for Stage 6 admin module.
 * Provides pure numerical metrics from local database records without external API calls or personal data lists.
 */
class MembershipStats
{
    /**
     * Number of new trials started within the given period.
     */
    public function trialsStarted(CarbonInterface $from, CarbonInterface $to): int
    {
        return User::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('trial_ends_at')
            ->count();
    }

    /**
     * Currently active trial accounts.
     */
    public function trialsActive(): int
    {
        return User::query()
            ->where('trial_ends_at', '>', now())
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->whereIn('status', ['active', 'canceled'])->where('paid_until', '>', now())->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
            })
            ->whereDoesntHave('giftMemberships', function ($query) {
                $query->where('expires_at', '>', now());
            })
            ->count();
    }

    /**
     * Number of accounts that converted from trial to a paid subscription or redeemed gift within the period.
     */
    public function trialsConverted(CarbonInterface $from, CarbonInterface $to): int
    {
        $subscribedCount = User::query()
            ->whereHas('subscriptions', function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from, $to])->whereNotNull('paid_until');
            })
            ->count();

        $giftCount = GiftMembership::query()
            ->whereBetween('redeemed_at', [$from, $to])
            ->count();

        return $subscribedCount + $giftCount;
    }

    /**
     * Number of trials that expired within the given period without converting to a paid subscription or gift.
     */
    public function trialsExpired(CarbonInterface $from, CarbonInterface $to): int
    {
        return User::query()
            ->whereBetween('trial_ends_at', [$from, $to])
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->whereIn('status', ['active', 'canceled'])->where('paid_until', '>', now())->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
            })
            ->whereDoesntHave('giftMemberships', function ($query) {
                $query->where('expires_at', '>', now());
            })
            ->count();
    }

    /**
     * Active paying members broken down by membership tier ('solidar', 'oase', 'maezen').
     *
     * @return array<string, int>
     */
    public function activeMembersByTier(): array
    {
        $tiers = array_keys(config('oasis.tiers', [
            'solidar' => [],
            'oase' => [],
            'maezen' => [],
        ]));

        /** @var array<string, int> $result */
        $result = array_fill_keys($tiers, 0);

        // Subscriptions
        $activeSubs = $this->activeSubscriptions()->get();

        foreach ($activeSubs as $sub) {
            $tier = $sub->type;
            if (! in_array($tier, $tiers, true)) {
                $tier = 'oase';
            }
            $result[$tier]++;
        }

        // Active gift memberships
        $activeGifts = GiftMembership::query()
            ->where('expires_at', '>', now())
            ->get();

        foreach ($activeGifts as $gift) {
            $tier = in_array($gift->tier, $tiers, true) ? $gift->tier : 'oase';
            $result[$tier]++;
        }

        return $result;
    }

    /**
     * Total number of active members (subscriptions + active gifts).
     */
    public function activeMembersCount(): int
    {
        $subCount = $this->activeSubscriptions()
            ->distinct('user_id')
            ->count('user_id');

        $giftCount = GiftMembership::query()
            ->where('expires_at', '>', now())
            ->distinct('redeemed_by_user_id')
            ->count('redeemed_by_user_id');

        return $subCount + $giftCount;
    }

    /**
     * Subscriptions that currently grant membership: paid up and not ended (a cancelled one runs until its end).
     *
     * @return Builder<Subscription>
     */
    private function activeSubscriptions(): Builder
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'canceled'])->where('paid_until', '>', now())->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Monthly recurring revenue (MRR) in euros derived from local subscription records.
     */
    public function monthlyRecurringRevenue(): float
    {
        return round(Subscription::query()->where('status', 'active')->whereNull('ends_at')
            ->where('paid_until', '>', now())->get()->sum(
                fn (Subscription $subscription) => $subscription->amount / 100 / ($subscription->rhythm === 'jahr' ? 12 : 1)
            ), 2);
    }

    /**
     * Number of subscription cancellations recorded within the given period.
     */
    public function cancellations(CarbonInterface $from, CarbonInterface $to): int
    {
        return Subscription::query()
            ->whereNotNull('ends_at')
            ->whereBetween('updated_at', [$from, $to])
            ->count();
    }

    /**
     * Number of founding member seats claimed.
     */
    public function foundingSeatsUsed(): int
    {
        return User::whereNotNull('founding_member_at')->count();
    }

    /**
     * Remaining founding seats available with price guarantee.
     */
    public function foundingSeatsRemaining(): int
    {
        $seats = (int) config('oasis.founding.seats', 1000);
        $used = $this->foundingSeatsUsed();

        return max(0, $seats - $used);
    }

    /**
     * Number of gift memberships sold within the given period.
     */
    public function giftsSold(CarbonInterface $from, CarbonInterface $to): int
    {
        return GiftMembership::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    /**
     * Number of gift memberships redeemed within the given period.
     */
    public function giftsRedeemed(CarbonInterface $from, CarbonInterface $to): int
    {
        return GiftMembership::query()
            ->whereNotNull('redeemed_at')
            ->whereBetween('redeemed_at', [$from, $to])
            ->count();
    }
}
