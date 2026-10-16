<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Local paid entitlement and recurring Mollie agreement, used throughout membership and account flows.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property string $rhythm
 * @property int $amount
 * @property string|null $mollie_id
 * @property Carbon|null $ends_at
 * @property Carbon|null $paid_until
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'type',
    'status',
    'rhythm',
    'amount',
    'mollie_id',
    'ends_at',
    'paid_until',
    'created_at',
    'updated_at',
])]
class Subscription extends Model
{
    /** Dates represent paid access, independently of whether future charges have been cancelled. */
    protected function casts(): array
    {
        return ['ends_at' => 'datetime', 'paid_until' => 'datetime'];
    }

    /**
     * The account owning this agreement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Only paid periods grant access; cancellation retains access through the paid period. */
    public function valid(): bool
    {
        return in_array($this->status, ['active', 'canceled'], true)
            && ($this->ends_at === null || $this->ends_at->isFuture())
            && $this->paid_until !== null && $this->paid_until->isFuture();
    }

    /** Whether future automatic payments have stopped. */
    public function canceled(): bool
    {
        return $this->ends_at !== null;
    }

    /** Whether a cancelled member still has prepaid access. */
    public function onGracePeriod(): bool
    {
        return $this->canceled() && $this->valid();
    }
}
