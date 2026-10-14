<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\HasNetwork;
use App\Models\Concerns\HasSafety;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Oasis account used by Fortify authentication, app access and account settings.
 *
 * @property Role $role
 * @property int $id
 * @property string $display_name
 * @property string|null $username
 * @property Carbon|null $birth_date
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $trial_reminder_sent_at
 * @property Carbon|null $founding_member_at
 * @property bool $founding_price_guarantee
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'display_name',
    'username',
    'email',
    'birth_date',
    'password',
    'terms_accepted_at',
    'trial_ends_at',
    'trial_reminder_sent_at',
    'founding_member_at',
    'founding_price_guarantee',
    'stripe_id',
    'pm_type',
    'pm_last_four',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    // Stage 1 & 5: Stripe payments and subscription management.
    use Billable;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Stage 2: profile, Tropfen, follows, oases, invitations.
    use HasNetwork;
    use HasSafety;
    use TwoFactorAuthenticatable;

    /** New account objects use the same least-privilege default as the database. */
    protected $attributes = ['role' => 'member'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'email_verified_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'terms_accepted_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'founding_member_at' => 'datetime',
            'founding_price_guarantee' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->display_name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    // -------------------------------------------------------------------------
    // Stage 1 & 5: Membership, payments, founding seats, participation state
    // -------------------------------------------------------------------------

    /**
     * Determine whether the user is allowed to actively participate (post, reply, react, chat, create oases).
     * Returns false if the trial has expired without an active subscription or gift.
     */
    public function canParticipate(): bool
    {
        if ($this->role?->isStaff()) {
            return true;
        }

        if ($this->hasActiveTrial()) {
            return true;
        }

        if ($this->hasActiveMembership()) {
            return true;
        }

        if ($this->hasActiveGiftMembership()) {
            return true;
        }

        return false;
    }

    /**
     * Whether the account is currently within its free trial window.
     */
    public function hasActiveTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Start or synchronize the trial for this user as part of a group start.
     */
    public function startGroupTrial(?int $trialDays = null): void
    {
        $days = $trialDays ?? (int) config('oasis.trial_days', 30);
        $this->update([
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * Get a subscription instance by type.
     * When type is null, returns the most recent subscription matching any Oasis tier or 'default'.
     */
    public function subscription(?string $type = null): ?Subscription
    {
        if ($type === null) {
            return $this->subscriptions->sortByDesc('created_at')->first(
                fn ($s) => in_array($s->type, ['default', 'solidar', 'oase', 'maezen'], true)
            );
        }

        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Whether the account currently has an active, paid Stripe subscription (including grace periods).
     */
    public function hasActiveMembership(): bool
    {
        $subscription = $this->subscription();

        return $subscription !== null && $subscription->valid();
    }

    /**
     * Whether the account has redeemed a gift membership that is currently active.
     */
    public function hasActiveGiftMembership(): bool
    {
        return $this->giftMemberships()->where('expires_at', '>', now())->exists();
    }

    /**
     * Whether this account is a recognized founding member.
     */
    public function isFoundingMember(): bool
    {
        return $this->founding_member_at !== null;
    }

    /**
     * Return the active membership tier key ('solidar', 'oase', 'maezen') or null if not subscribed.
     */
    public function currentMembershipTier(): ?string
    {
        $subscription = $this->subscription();

        if ($subscription && $subscription->valid()) {
            // Check if subscription type is a specific tier name
            if (in_array($subscription->type, ['solidar', 'oase', 'maezen'], true)) {
                return $subscription->type;
            }

            // Check subscription items metadata/price
            $item = $subscription->items->first();
            if ($item) {
                foreach (array_keys(config('oasis.tiers', [])) as $tierKey) {
                    if (str_contains(strtolower($item->stripe_price ?? ''), $tierKey)
                        || str_contains(strtolower($item->stripe_product ?? ''), $tierKey)) {
                        return $tierKey;
                    }
                }
            }

            // Default fallback if active subscription exists without explicit tier
            return 'oase';
        }

        $activeGift = $this->giftMemberships()->where('expires_at', '>', now())->latest('expires_at')->first();
        if ($activeGift) {
            return $activeGift->tier;
        }

        return null;
    }

    /**
     * Gift memberships redeemed by this user.
     *
     * @return HasMany<GiftMembership, $this>
     */
    public function giftMemberships(): HasMany
    {
        return $this->hasMany(GiftMembership::class, 'redeemed_by_user_id');
    }

    /**
     * Gift memberships purchased by this user for others.
     *
     * @return HasMany<GiftMembership, $this>
     */
    public function purchasedGiftMemberships(): HasMany
    {
        return $this->hasMany(GiftMembership::class, 'purchaser_user_id');
    }
}
