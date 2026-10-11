<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Published figures of one month for the public Transparenz page: paying members, sponsored seats, income and the
 * running costs per item. Admins publish them in the admin area; the newest report is shown.
 *
 * @property int $id
 * @property Carbon $month
 * @property int $members
 * @property int $sponsored_seats
 * @property string $income
 * @property array<string, float> $cost_items
 * @property Carbon $updated_at
 */
class TransparencyReport extends Model
{
    protected $fillable = ['month', 'members', 'sponsored_seats', 'income', 'cost_items', 'published_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'date', 'income' => 'decimal:2', 'cost_items' => 'array'];
    }

    /** Sum of all cost items in euro. */
    public function costs(): float
    {
        return (float) array_sum($this->cost_items);
    }

    /** Income / costs in percent, or null without costs. */
    public function coverage(): ?int
    {
        return $this->costs() > 0 ? (int) round((float) $this->income / $this->costs() * 100) : null;
    }
}
