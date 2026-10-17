<?php

namespace App\Actions\Admin;

use App\Models\TransparencyReport;
use App\Models\User;
use App\Queries\Membership\MembershipStats;

/**
 * Publish one month on the public Transparenz page. Members, carried seats and income are taken from the real
 * memberships at the moment of publishing, so nobody can type in figures; the admin only provides the costs.
 * Publishing a month again corrects it; cost items without a name are dropped, the rest are rounded to cents.
 */
class PublishTransparencyReport
{
    public function __construct(private readonly MembershipStats $membership) {}

    /**
     * @param  string  $month  validated, Y-m
     * @param  array<int, array{label: ?string, amount: mixed}>  $items  validated cost rows, empty rows allowed
     * @return bool false when no named cost item remains, so nothing was published
     */
    public function handle(User $admin, string $month, array $items): bool
    {
        $costs = collect($items)->filter(fn (array $item) => filled($item['label'] ?? null))
            ->mapWithKeys(fn (array $item) => [trim($item['label']) => round((float) $item['amount'], 2)])->all();
        if ($costs === []) {
            return false;
        }

        TransparencyReport::updateOrCreate(['month' => $month.'-01'], [
            'members' => $this->membership->activeMembersCount(),
            'sponsored_seats' => $this->membership->sponsoredSeats(),
            'income' => $this->membership->monthlyRecurringRevenue(),
            'cost_items' => $costs, 'published_by' => $admin->id,
        ]);

        return true;
    }
}
