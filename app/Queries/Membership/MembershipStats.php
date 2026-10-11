<?php

namespace App\Queries\Membership;

use App\Models\GiftMembership;
use App\Models\User;
use Carbon\CarbonInterface;
use Laravel\Cashier\Subscription;

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
                $query->whereIn('stripe_status', ['active', 'trialing']);
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
                $query->whereBetween('created_at', [$from, $to]);
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
                $query->whereIn('stripe_status', ['active', 'trialing']);
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

        $result = array_fill_keys($tiers, 0);

        // Subscriptions
        $activeSubs = Subscription::query()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->get();

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
        $subCount = Subscription::query()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->distinct('user_id')
            ->count('user_id');

        $giftCount = GiftMembership::query()
            ->where('expires_at', '>', now())
            ->distinct('redeemed_by_user_id')
            ->count('redeemed_by_user_id');

        return $subCount + $giftCount;
    }

    /**
     * Monthly recurring revenue (MRR) in euros derived from local subscription records.
     */
    public function monthlyRecurringRevenue(): float
    {
        $subscriptions = Subscription::query()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->with('items')
            ->get();

        $tiers = config('oasis.tiers', []);
        $totalMrr = 0.0;

        foreach ($subscriptions as $sub) {
            $tierKey = in_array($sub->type, array_keys($tiers), true) ? $sub->type : 'oase';
            $tierData = $tiers[$tierKey] ?? ['monthly' => 5];

            // Check if monthly or yearly from price lookup or items
            $isYearly = false;
            $customMonthly = null;

            foreach ($sub->items as $item) {
                if (str_contains(strtolower($item->stripe_price ?? ''), 'yearly')
                    || str_contains(strtolower($item->stripe_price ?? ''), 'jahr')) {
                    $isYearly = true;
                }

                // Check for custom amount in lookup key e.g. oasis_maezen_monthly_25
                if (preg_match('/oasis_maezen_(monthly|yearly)_(\d+)/', $item->stripe_price ?? '', $matches)) {
                    $customMonthly = (float) $matches[2];
                    if ($matches[1] === 'yearly') {
                        $isYearly = true;
                    }
                }
            }

            if ($customMonthly !== null) {
                $totalMrr += $isYearly ? ($customMonthly / 12) : $customMonthly;
            } else {
                $monthly = (float) ($tierData['monthly'] ?? 5);
                if ($isYearly) {
                    $yearMonths = (float) config('oasis.year_months', 10);
                    $yearlyPrice = $monthly * $yearMonths;
                    $totalMrr += ($yearlyPrice / 12);
                } else {
                    $totalMrr += $monthly;
                }
            }
        }

        return round($totalMrr, 2);
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
