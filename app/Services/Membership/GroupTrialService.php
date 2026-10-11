<?php

namespace App\Services\Membership;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Service managing group trials ("Gemeinsam starten").
 *
 * As described on the Gemeinsam page:
 * - A group has up to `config('oasis.group_start_days')` (7 days) to gather all members.
 * - Once the group is complete (or after the 7 days expire), the 30-day trial starts
 *   simultaneously for all group members.
 */
class GroupTrialService
{
    /**
     * Start the free trial for a group of users simultaneously.
     *
     * @param  iterable<User>  $users
     */
    public function startGroupTrial(iterable $users, ?int $trialDays = null): void
    {
        $days = $trialDays ?? (int) config('oasis.trial_days', 30);
        $newTrialEnd = now()->addDays($days);

        $collection = $users instanceof Collection ? $users : collect($users);

        foreach ($collection as $user) {
            $user->update([
                'trial_ends_at' => $newTrialEnd,
            ]);
        }
    }

    /**
     * Calculate days remaining in the group formation window before the group trial auto-starts.
     */
    public function daysRemainingToStartGroup(CarbonInterface|\DateTimeInterface $groupCreatedAt): int
    {
        $groupStartDays = (int) config('oasis.group_start_days', 7);
        $autoStartAt = now()->parse($groupCreatedAt)->addDays($groupStartDays);

        if (now()->greaterThanOrEqualTo($autoStartAt)) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($autoStartAt, false));
    }

    /**
     * Whether the group formation window has expired and the group trial should automatically begin.
     */
    public function shouldAutoStartGroup(CarbonInterface|\DateTimeInterface $groupCreatedAt): bool
    {
        return $this->daysRemainingToStartGroup($groupCreatedAt) === 0;
    }
}
