<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Checkout intent and verified payment ledger; never trust webhook metadata as an order.
 *
 * A paid record is an accounting document (§ 147 AO, § 257 HGB): it outlives a deleted account (user_id becomes null)
 * with its invoice snapshot and Mollie ids, is used for nothing but bookkeeping and the admin payment search, and is
 * pruned once `oasis.privacy.payment_records_years` have passed after the end of its calendar year. Checkout attempts
 * that were never paid are no documents and are pruned after 90 days.
 *
 * @property int $id
 * @property int|null $user_id null once the account was deleted
 * @property int|null $subscription_id
 * @property string|null $mollie_payment_id
 * @property string|null $mollie_customer_id
 * @property string $kind
 * @property string $tier
 * @property string $rhythm
 * @property int $amount
 * @property string $status
 * @property bool $refunded
 * @property array<string, mixed>|null $invoice_data
 * @property Carbon|null $processed_at
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'user_id',
    'subscription_id',
    'mollie_payment_id',
    'mollie_customer_id',
    'kind',
    'tier',
    'rhythm',
    'amount',
    'status',
    'refunded',
    'invoice_data',
    'processed_at',
    'paid_at',
])]
class MembershipPayment extends Model
{
    use MassPrunable;

    /** Days after which a checkout attempt that was never paid is removed. */
    public const UNPAID_DAYS = 90;

    /**
     * Mollie statuses that can still change ("paid" while fulfilment is not recorded yet). expired, canceled and failed
     * are final: such records are never asked for again.
     */
    public const UNSETTLED_STATUSES = ['open', 'pending', 'authorized', 'paid'];

    /** Fulfillment is persisted separately to make repeated notifications harmless. */
    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'paid_at' => 'datetime', 'refunded' => 'boolean', 'invoice_data' => 'array'];
    }

    /**
     * Records the Mollie sync still has to ask about: a Mollie payment exists, the result is not processed and the
     * status can still change.
     *
     * @param  Builder<MembershipPayment>  $query
     */
    public function scopeUnsettled(Builder $query): void
    {
        $query->whereNull('processed_at')->whereNotNull('mollie_payment_id')->whereIn('status', self::UNSETTLED_STATUSES);
    }

    /** Whether Mollie may still report a different status for this record. */
    public function isUnsettled(): bool
    {
        return $this->processed_at === null && $this->mollie_payment_id !== null && in_array($this->status, self::UNSETTLED_STATUSES, true);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Paid records after the statutory period (counted from the end of their calendar year), unpaid attempts after
     * UNPAID_DAYS. Nothing paid is pruned while the period is not configured.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $years = config('oasis.privacy.payment_records_years');

        return static::query()->where(fn (Builder $old) => $old
            ->where(fn (Builder $unpaid) => $unpaid->whereNull('paid_at')->where('created_at', '<', now()->subDays(self::UNPAID_DAYS)))
            ->when($years !== null, fn (Builder $records) => $records->orWhere('paid_at', '<', now()->subYears((int) $years)->startOfYear())));
    }
}
