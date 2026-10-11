<?php

namespace App\Models;

use Database\Factories\GiftMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A gift membership voucher that can be redeemed by any Oasis member to activate membership.
 *
 * @property int $id
 * @property string $code
 * @property string $tier
 * @property int $months
 * @property int $amount
 * @property string $currency
 * @property int|null $purchaser_user_id
 * @property string|null $purchaser_email
 * @property string|null $stripe_checkout_session_id
 * @property int|null $redeemed_by_user_id
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'tier',
    'months',
    'amount',
    'currency',
    'purchaser_user_id',
    'purchaser_email',
    'stripe_checkout_session_id',
    'redeemed_by_user_id',
    'redeemed_at',
    'expires_at',
])]
class GiftMembership extends Model
{
    /** @use HasFactory<GiftMembershipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
            'months' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * The member who purchased this gift, if logged in at checkout.
     *
     * @return BelongsTo<User, $this>
     */
    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    /**
     * The member who redeemed this gift code.
     *
     * @return BelongsTo<User, $this>
     */
    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    /**
     * Whether this voucher has already been redeemed.
     */
    public function isRedeemed(): bool
    {
        return $this->redeemed_at !== null;
    }

    /**
     * Whether this gift membership is currently active for the recipient.
     */
    public function isActive(): bool
    {
        return $this->isRedeemed() && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    /**
     * Generate a formatted, human-readable unique voucher code.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'OASIS-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
