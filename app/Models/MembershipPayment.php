<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Checkout intent and verified payment ledger; never trust webhook metadata as an order.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $subscription_id
 * @property string|null $mollie_payment_id
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
    /** Fulfillment is persisted separately to make repeated notifications harmless. */
    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'paid_at' => 'datetime', 'refunded' => 'boolean', 'invoice_data' => 'array'];
    }
}
