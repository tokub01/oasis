<?php

namespace App\Services\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Mail\Membership\MembershipCancelledMail;
use App\Mail\Membership\MembershipWithdrawnMail;
use App\Models\GiftMembership;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * Service for managing Stripe products, prices, checkout sessions, customer portal,
 * founding member allocation, and cancellation / withdrawal workflows.
 */
class StripeMembershipService
{
    /**
     * Resolve the StripeClient from Cashier or container.
     */
    protected function stripe(): StripeClient
    {
        return app()->bound(StripeClient::class)
            ? app(StripeClient::class)
            : Cashier::stripe();
    }

    /**
     * Synchronize tiers and prices from config('oasis.tiers') to Stripe in test mode.
     * Idempotently creates or finds products and prices.
     *
     * @return array<string, array<string, string>> Array of tier => ['product_id' => ..., 'monthly_price_id' => ..., 'yearly_price_id' => ...]
     */
    public function syncProductsAndPrices(): array
    {
        $stripe = $this->stripe();
        $tiers = config('oasis.tiers', []);
        $yearMonths = (int) config('oasis.year_months', 10);
        $result = [];

        foreach ($tiers as $tierKey => $tierData) {
            $productName = 'Oasis '.$tierData['name'];
            $productId = null;

            // Search for existing product with metadata or name
            $products = $stripe->products->search([
                'query' => "metadata['oasis_tier']:'{$tierKey}'",
            ]);

            if (count($products->data) > 0) {
                $productId = $products->data[0]->id;
            } else {
                $created = $stripe->products->create([
                    'name' => $productName,
                    'description' => "Oasis Mitgliedschaft – Stufe {$tierData['name']}",
                    'metadata' => [
                        'oasis_tier' => $tierKey,
                    ],
                ]);
                $productId = $created->id;
            }

            // Sync monthly price
            $monthlyLookup = "oasis_{$tierKey}_monthly";
            $monthlyPriceId = $this->syncPrice(
                $productId,
                (int) ($tierData['monthly'] * 100),
                'month',
                $monthlyLookup,
                ['oasis_tier' => $tierKey, 'oasis_rhythm' => 'monthly']
            );

            // Sync yearly price (monthly * year_months)
            $yearlyLookup = "oasis_{$tierKey}_yearly";
            $yearlyPriceId = $this->syncPrice(
                $productId,
                (int) ($tierData['monthly'] * $yearMonths * 100),
                'year',
                $yearlyLookup,
                ['oasis_tier' => $tierKey, 'oasis_rhythm' => 'yearly']
            );

            $result[$tierKey] = [
                'product_id' => $productId,
                'monthly_price_id' => $monthlyPriceId,
                'yearly_price_id' => $yearlyPriceId,
            ];
        }

        return $result;
    }

    /**
     * Idempotently find or create a recurring Stripe price by lookup key.
     *
     * @param  array<string, string>  $metadata
     */
    protected function syncPrice(string $productId, int $amountInCents, string $interval, string $lookupKey, array $metadata = []): string
    {
        $stripe = $this->stripe();

        $existing = $stripe->prices->all([
            'lookup_keys' => [$lookupKey],
            'limit' => 1,
        ]);

        if (count($existing->data) > 0) {
            return $existing->data[0]->id;
        }

        $created = $stripe->prices->create([
            'product' => $productId,
            'unit_amount' => $amountInCents,
            'currency' => 'eur',
            'recurring' => ['interval' => $interval],
            'lookup_key' => $lookupKey,
            'tax_behavior' => 'inclusive',
            'metadata' => $metadata,
        ]);

        return $created->id;
    }

    /**
     * Get or create price ID for a given tier and rhythm.
     * For Mäzen custom amounts ≥ 12 €, generates an on-demand recurring price linked to the canonical Mäzen product.
     */
    public function getPriceIdForTier(string $tier, string $rhythm = 'monat', ?int $customMonthlyAmount = null): string
    {
        $interval = $rhythm === 'jahr' ? 'yearly' : 'monthly';
        $stripeInterval = $rhythm === 'jahr' ? 'year' : 'month';
        $yearMonths = (int) config('oasis.year_months', 10);

        if ($tier === 'maezen' && $customMonthlyAmount !== null) {
            $minMaezen = (int) config('oasis.tiers.maezen.monthly', 12);
            $monthlyAmount = max($minMaezen, $customMonthlyAmount);
            $amountInCents = $rhythm === 'jahr'
                ? $monthlyAmount * $yearMonths * 100
                : $monthlyAmount * 100;

            $lookupKey = "oasis_maezen_{$interval}_{$monthlyAmount}";

            $stripe = $this->stripe();
            $existing = $stripe->prices->all([
                'lookup_keys' => [$lookupKey],
                'limit' => 1,
            ]);

            if (count($existing->data) > 0) {
                return $existing->data[0]->id;
            }

            // Find Mäzen product
            $sync = $this->syncProductsAndPrices();
            $productId = $sync['maezen']['product_id'];

            $created = $stripe->prices->create([
                'product' => $productId,
                'unit_amount' => $amountInCents,
                'currency' => 'eur',
                'recurring' => ['interval' => $stripeInterval],
                'lookup_key' => $lookupKey,
                'tax_behavior' => 'inclusive',
                'metadata' => [
                    'oasis_tier' => 'maezen',
                    'oasis_rhythm' => $interval,
                    'custom_monthly' => (string) $monthlyAmount,
                ],
            ]);

            return $created->id;
        }

        $lookupKey = "oasis_{$tier}_{$interval}";
        $existing = $this->stripe()->prices->all([
            'lookup_keys' => [$lookupKey],
            'limit' => 1,
        ]);

        if (count($existing->data) > 0) {
            return $existing->data[0]->id;
        }

        // Run sync if not yet created
        $sync = $this->syncProductsAndPrices();

        return $rhythm === 'jahr'
            ? $sync[$tier]['yearly_price_id']
            : $sync[$tier]['monthly_price_id'];
    }

    /**
     * Create a hosted Stripe Checkout Session for subscription checkout.
     */
    public function createSubscriptionCheckout(
        User $user,
        string $tier,
        string $rhythm = 'monat',
        ?int $customAmount = null,
        string $paymentMethod = 'sepa',
    ): string {
        $priceId = $this->getPriceIdForTier($tier, $rhythm, $customAmount);

        $paymentTypes = match ($paymentMethod) {
            'sepa' => ['sepa_debit', 'card'],
            'paypal' => ['paypal', 'card'],
            default => ['card'],
        };

        try {
            return $this->buildCheckoutSessionUrl($user, $tier, $rhythm, $priceId, $paymentTypes);
        } catch (InvalidRequestException $e) {
            Log::warning('Stripe checkout failed; retrying with card fallback.', [
                'exception_class' => $e::class, 'provider_code' => $e->getStripeCode(),
            ]);

            // Fallback to basic card if specific payment method type is not activated in dashboard
            return $this->buildCheckoutSessionUrl($user, $tier, $rhythm, $priceId, ['card']);
        }
    }

    /**
     * Helper to initiate Cashier checkout session and return the redirect URL.
     *
     * @param  array<string>  $paymentTypes
     */
    protected function buildCheckoutSessionUrl(User $user, string $tier, string $rhythm, string $priceId, array $paymentTypes): string
    {
        $session = $user->newSubscription($tier, $priceId)
            ->checkout([
                'payment_method_types' => $paymentTypes,
                'success_url' => route('app.membership', ['checkout' => 'success']),
                'cancel_url' => route('oasis.mitglied'),
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'oasis_tier' => $tier,
                    'oasis_rhythm' => $rhythm,
                ],
            ]);

        return $session->url;
    }

    /**
     * Create a Checkout session for purchasing a 12-month gift membership voucher.
     */
    public function createGiftCheckout(User $purchaser, string $tier, int $months = 12): string
    {
        $tiers = config('oasis.tiers', []);
        $tierConfig = $tiers[$tier] ?? $tiers['oase'];
        $yearMonths = (int) config('oasis.year_months', 10);

        // Price for 12 months with 2 months free as configured
        $amountInCents = (int) ($tierConfig['monthly'] * $yearMonths * 100);

        $session = $purchaser->checkoutCharge($amountInCents, 'Oasis Geschenk-Gutschein (Stufe: '.$tierConfig['name'].')', 1, [
            'payment_method_types' => ['card', 'sepa_debit'],
            'success_url' => route('app.membership', ['gift_purchased' => 1]),
            'cancel_url' => route('app.membership'),
            'metadata' => [
                'type' => 'gift_membership',
                'purchaser_id' => (string) $purchaser->id,
                'purchaser_email' => $purchaser->email,
                'tier' => $tier,
                'months' => (string) $months,
                'amount' => (string) $amountInCents,
            ],
        ]);

        return $session->url;
    }

    /**
     * Fulfill a gift membership purchase from checkout metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillGiftPurchase(array $metadata, string $sessionId): GiftMembership
    {
        // firstOrCreate recovers a competing insert through the database unique constraint.
        return GiftMembership::firstOrCreate(['stripe_checkout_session_id' => $sessionId], [
            'code' => GiftMembership::generateCode(),
            'tier' => $metadata['tier'] ?? 'oase',
            'months' => (int) ($metadata['months'] ?? 12),
            'amount' => (int) ($metadata['amount'] ?? 5000),
            'currency' => 'eur',
            'purchaser_user_id' => isset($metadata['purchaser_id']) ? (int) $metadata['purchaser_id'] : null,
            'purchaser_email' => $metadata['purchaser_email'] ?? null,
        ]);
    }

    /**
     * Redeem a gift membership voucher for a recipient user.
     */
    public function redeemGift(User $user, string $code): GiftMembership
    {
        $normalizedCode = strtoupper(trim($code));

        $gift = GiftMembership::where('code', $normalizedCode)->first();

        if (! $gift) {
            throw new MembershipDomainException('Dieser Gutscheincode ist ungültig.');
        }

        if ($gift->isRedeemed()) {
            throw new MembershipDomainException('Dieser Gutscheincode wurde bereits eingelöst.');
        }

        $gift->update([
            'redeemed_by_user_id' => $user->id,
            'redeemed_at' => now(),
            'expires_at' => now()->addMonths($gift->months),
        ]);

        // Assign founding member if eligible
        $this->assignFoundingMemberIfEligible($user);

        return $gift;
    }

    /**
     * Cancel an active subscription at period end (§ 312k BGB compliant flow).
     * Sends confirmation email to user and copy to operator.
     */
    public function cancelSubscription(User $user): CarbonInterface|\DateTimeInterface
    {
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid()) {
            throw new MembershipDomainException('Es wurde kein aktiver Vertrag zum Kündigen gefunden.');
        }

        // Cancel at end of billing period
        $subscription->cancel();

        if ($subscription->ends_at === null) {
            $subscription->update([
                'ends_at' => now()->addMonth(),
            ]);
        }

        // Effective date is the period end
        $effectiveDate = $subscription->ends_at ?? now()->addMonth();
        $tierName = config('oasis.tiers.'.$user->currentMembershipTier().'.name', 'Oasis');

        // Send text-form confirmation (§ 312k BGB)
        Mail::to($user->email)->send(new MembershipCancelledMail($user, $tierName, $effectiveDate));
        Mail::to(config('oasis.mail.operator'))->send(new MembershipCancelledMail($user, $tierName, $effectiveDate, operatorCopy: true));

        return $effectiveDate;
    }

    /**
     * Withdraw an active subscription within 14 days of initial booking (§ 355, 356 BGB).
     * Cancels immediately and refunds payments (§ 357 Abs. 1 BGB).
     */
    public function withdrawSubscription(User $user): void
    {
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid()) {
            throw new MembershipDomainException('Es wurde kein aktiver Vertrag zum Widerrufen gefunden.');
        }

        // Check statutory 14-day window: starts on conclusion and expires at end of 14th calendar day
        $createdAt = $subscription->created_at;
        if ($createdAt && now()->greaterThan($createdAt->copy()->addDays(14)->endOfDay())) {
            throw new MembershipDomainException('Die gesetzliche 14-tägige Widerrufsfrist ist bereits abgelaufen.');
        }

        // Cancel immediately
        $subscription->cancelNow();

        // Refund paid invoices per § 357 Abs. 1 BGB
        $this->refundSubscriptionInvoices($subscription);

        $tierName = config('oasis.tiers.'.$user->currentMembershipTier().'.name', 'Oasis');

        // Send confirmation email
        Mail::to($user->email)->send(new MembershipWithdrawnMail($user, $tierName));
        Mail::to(config('oasis.mail.operator'))->send(new MembershipWithdrawnMail($user, $tierName, operatorCopy: true));
    }

    /**
     * Refund all paid invoices for a withdrawn subscription (§ 357 Abs. 1 BGB).
     */
    public function refundSubscriptionInvoices(Subscription $subscription): void
    {
        if (empty($subscription->stripe_id)) {
            return;
        }

        try {
            $invoices = $this->stripe()->invoices->all([
                'subscription' => $subscription->stripe_id,
                'status' => 'paid',
                'limit' => 10,
            ]);

            foreach ($invoices->data as $invoice) {
                if (! empty($invoice->payment_intent)) {
                    $this->stripe()->refunds->create([
                        'payment_intent' => $invoice->payment_intent,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // The member was promised a refund (§ 357 BGB): the operator must refund manually within 14 days.
            Log::error('Refund failed for subscription withdrawal.', [
                'exception_class' => $e::class,
                'provider_code' => $e instanceof ApiErrorException ? $e->getStripeCode() : null,
            ]);
            Mail::raw(
                'Die automatische Erstattung nach einem Widerruf ist fehlgeschlagen (Stripe-Abo '.$subscription->stripe_id
                .'). Bitte innerhalb von 14 Tagen manuell im Stripe-Dashboard erstatten.',
                fn ($message) => $message->to(config('oasis.mail.operator'))->subject('Oasis: Erstattung manuell nachholen'),
            );
        }
    }

    /**
     * Check founding member seats and award status if under the configured seat limit.
     */
    public function assignFoundingMemberIfEligible(User $user): bool
    {
        if ($user->isFoundingMember()) {
            return false;
        }

        return DB::transaction(function () use ($user) {
            $seatsLimit = (int) config('oasis.founding.seats', 1000);
            $currentFoundingCount = User::whereNotNull('founding_member_at')->lockForUpdate()->count();

            if ($currentFoundingCount < $seatsLimit) {
                $user->update([
                    'founding_member_at' => now(),
                    'founding_price_guarantee' => true,
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Total remaining founding seats with price guarantee.
     */
    public function remainingFoundingSeats(): int
    {
        $seatsLimit = (int) config('oasis.founding.seats', 1000);
        $used = User::whereNotNull('founding_member_at')->count();

        return max(0, $seatsLimit - $used);
    }

    /**
     * Generate Stripe customer billing portal URL.
     */
    public function getBillingPortalUrl(User $user): string
    {
        return $user->billingPortalUrl(route('app.membership'));
    }
}
