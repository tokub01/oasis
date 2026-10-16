<?php

namespace App\Actions\Admin;

use App\Models\TransparencyReport;
use App\Models\User;

/**
 * Publish one month's figures on the public Transparenz page. Publishing a month again corrects it; cost items
 * without a name are dropped, the rest are rounded to cents.
 */
class PublishTransparencyReport
{
    /**
     * @param  array{month: string, members: int, sponsored_seats: int, income: float|int|string}  $figures  validated, month as Y-m
     * @param  array<int, array{label: ?string, amount: mixed}>  $items  validated cost rows, empty rows allowed
     * @return bool false when no named cost item remains, so nothing was published
     */
    public function handle(User $admin, array $figures, array $items): bool
    {
        $costs = collect($items)->filter(fn (array $item) => filled($item['label'] ?? null))
            ->mapWithKeys(fn (array $item) => [trim($item['label']) => round((float) $item['amount'], 2)])->all();
        if ($costs === []) {
            return false;
        }

        TransparencyReport::updateOrCreate(['month' => $figures['month'].'-01'], [
            'members' => $figures['members'], 'sponsored_seats' => $figures['sponsored_seats'], 'income' => $figures['income'],
            'cost_items' => $costs, 'published_by' => $admin->id,
        ]);

        return true;
    }
}
