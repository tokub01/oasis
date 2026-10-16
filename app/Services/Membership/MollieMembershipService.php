<?php

namespace App\Services\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Mail\Membership\MembershipCancelledMail;
use App\Mail\Membership\MembershipWithdrawnMail;
use App\Models\GiftMembership;
use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service for managing Mollie checkout payments, recurring agreements,
 * founding member allocation, and cancellation / withdrawal workflows.
 */
class MollieMembershipService
{
    public function __construct(private readonly MollieGateway $gateway) {}

    /** Store the server-calculated order before sending it to Mollie's hosted checkout. */
    private function checkout(User $user, string $tier, string $rhythm, ?int $custom, string $method, string $kind, ?int $subscriptionId = null): string
    {
        $user = DB::transaction(function () use ($user, $kind): User {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($kind === 'membership' && $user->hasActiveMembership()) {
                throw new MembershipDomainException('Du hast bereits eine laufende Mitgliedschaft.');
            }
            if (! $user->hasMollieId()) {
                $user->update(['mollie_customer_id' => $this->gateway->customer($user)]);
            }

            return $user;
        });
        $monthly = $tier === 'maezen' && $custom !== null ? max((int) config('oasis.tiers.maezen.monthly'), $custom) : (int) config('oasis.tiers.'.$tier.'.monthly');
        $amount = $kind === 'mandate' ? 100 : $monthly * 100 * ($rhythm === 'jahr' ? (int) config('oasis.year_months') : 1);
        $intent = MembershipPayment::create([
            'user_id' => $user->id, 'subscription_id' => $subscriptionId, 'kind' => $kind,
            'tier' => $tier, 'rhythm' => $rhythm, 'amount' => $amount, 'status' => 'open',
            'invoice_data' => ['seller' => config('oasis.operator'), 'vat_rate' => config('oasis.vat_rate'),
                'customer_name' => $user->display_name, 'customer_email' => $user->email,
                'description' => $kind === 'mandate' ? 'Zahlungsmittelbestätigung' : 'Oasis '.config('oasis.tiers.'.$tier.'.name')],
        ]);
        // First direct debit is not supported: Mollie offers eligible methods that establish a mandate.
        $providerMethod = match ($method) {
            'paypal' => 'paypal', 'karte' => 'creditcard', default => null
        };
        $payment = $this->gateway->payment($intent, $user->mollie_customer_id, $providerMethod);
        $intent->update(['mollie_payment_id' => $payment->id]);

        return $payment->getCheckoutUrl();
    }

    /** Begin a paid membership; an API-verified paid webhook grants access. */
    public function createSubscriptionCheckout(User $user, string $tier, string $rhythm = 'monat', ?int $customAmount = null, string $paymentMethod = 'sepa'): string
    {
        return $this->checkout($user, $tier, $rhythm, $customAmount, $paymentMethod, 'membership');
    }

    /** Sell twelve months as a one-off payment without automatic renewal. */
    public function createGiftCheckout(User $purchaser, string $tier, int $months = 12): string
    {
        return $this->checkout($purchaser, $tier, 'jahr', null, 'sepa', 'gift');
    }

    /** Collect a new mandate via a refundable one-euro first payment. */
    public function changePaymentMethod(User $user, string $method): string
    {
        $subscription = $user->subscription();
        if (! $subscription || ! $subscription->valid() || $subscription->canceled()) {
            throw new MembershipDomainException('Du hast derzeit kein laufendes Abonnement.');
        }

        return $this->checkout($user, $subscription->type, $subscription->rhythm, null, $method, 'mandate', $subscription->id);
    }

    /** Process untrusted notifications by retrieving payment and checking it against a local order. */
    public function processPayment(string $id): void
    {
        $payment = $this->gateway->getPayment($id);
        DB::transaction(function () use ($payment, $id): void {
            $intent = MembershipPayment::where('mollie_payment_id', $id)->lockForUpdate()->first();
            if (! $intent && isset($payment->metadata->intent_id)) {
                $intent = MembershipPayment::whereKey($payment->metadata->intent_id)
                    ->whereNull('mollie_payment_id')->lockForUpdate()->first();
                if ($intent) {
                    $intent->update(['mollie_payment_id' => $id]);
                }
            }
            if (! $intent) {
                // Recurring notifications have no pre-existing checkout intent.
                $subscription = Subscription::where('mollie_id', $payment->subscriptionId ?? null)->lockForUpdate()->first();
                if (! $subscription || ! $subscription->mollie_id) {
                    return;
                }
                $owner = $subscription->user;
                if (($payment->customerId ?? null) !== $owner->mollie_customer_id) {
                    return;
                }
                $intent = MembershipPayment::firstOrCreate(['mollie_payment_id' => $id], [
                    'user_id' => $owner->id, 'subscription_id' => $subscription->id, 'kind' => 'renewal',
                    'tier' => $subscription->type, 'rhythm' => $subscription->rhythm, 'amount' => $subscription->amount, 'status' => 'open',
                    'invoice_data' => MembershipPayment::where('subscription_id', $subscription->id)->whereNotNull('invoice_data')->oldest('id')->value('invoice_data'),
                ]);
            }
            $user = User::whereKey($intent->user_id)->lockForUpdate()->first();
            if (! $user || $payment->id !== $id || ($payment->customerId ?? null) !== $user->mollie_customer_id
                || $payment->amount->currency !== 'EUR' || $payment->amount->value !== number_format($intent->amount / 100, 2, '.', '')) {
                throw new MembershipDomainException('Die Zahlungsdaten stimmen nicht mit der Bestellung überein.');
            }
            if ($intent->processed_at) {
                return;
            }
            $intent->update(['status' => $payment->status]);
            if ($payment->status !== 'paid' || ! $payment->isPaid()) {
                return;
            }
            if ($intent->kind === 'gift') {
                $this->fulfillGiftPurchase(['tier' => $intent->tier, 'months' => 12, 'amount' => $intent->amount,
                    'purchaser_id' => $user->id, 'purchaser_email' => $user->email], $id);
            } elseif ($intent->kind === 'mandate') {
                $subscription = Subscription::findOrFail($intent->subscription_id);
                if ($subscription->valid() && ! $subscription->canceled()) {
                    $this->gateway->changeMandate($user->mollie_customer_id, $subscription->mollie_id, $payment->mandateId, 'oasis-mandate-'.$intent->id);
                }
                $this->gateway->refund($id, $intent->amount);
                $intent->update(['refunded' => true]);
            } else {
                if ($intent->kind === 'membership' && $user->hasActiveMembership()) {
                    // Two checkout tabs must never leave the member with two recurring agreements.
                    $this->gateway->refund($id, $intent->amount);
                    $intent->update(['refunded' => true, 'processed_at' => now(), 'paid_at' => $payment->paidAt]);

                    return;
                }
                $paidAt = Carbon::parse($payment->paidAt);
                $subscription = $intent->subscription_id ? Subscription::findOrFail($intent->subscription_id) : Subscription::create([
                    'user_id' => $user->id, 'type' => $intent->tier, 'rhythm' => $intent->rhythm,
                    'amount' => $intent->amount, 'status' => 'active', 'created_at' => $paidAt,
                ]);
                $base = $subscription->paid_until && $subscription->paid_until->greaterThan($paidAt) ? $subscription->paid_until->copy() : $paidAt;
                $subscription->update(['paid_until' => $intent->rhythm === 'jahr' ? $base->addYearNoOverflow() : $base->addMonthNoOverflow()]);
                $intent->update(['subscription_id' => $subscription->id]);
                if (! $subscription->mollie_id) {
                    $subscription->update(['mollie_id' => $this->gateway->subscribe($subscription, $user->mollie_customer_id, $payment->mandateId, 'oasis-subscription-payment-'.$intent->id)]);
                }
                $this->assignFoundingMemberIfEligible($user);
            }
            $snapshot = $intent->invoice_data ?? [];
            $snapshot['billing_address'] = (array) ($payment->billingAddress ?? []);
            $intent->update(['processed_at' => now(), 'paid_at' => $payment->paidAt, 'invoice_data' => $snapshot]);
        });
    }

    /**
     * Fulfill a gift membership purchase from checkout metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillGiftPurchase(array $metadata, string $sessionId): GiftMembership
    {
        // firstOrCreate recovers a competing insert through the database unique constraint.
        return GiftMembership::firstOrCreate(['mollie_payment_id' => $sessionId], [
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
     * Redeem a voucher once; lock the code until redemption and founding allocation commit together.
     */
    public function redeemGift(User $user, string $code): GiftMembership
    {
        return DB::transaction(function () use ($user, $code): GiftMembership {
            $normalizedCode = strtoupper(trim($code));

            $gift = GiftMembership::where('code', $normalizedCode)->lockForUpdate()->first();

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
        });
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

        if (! $subscription->canceled()) {
            $this->gateway->cancel($user->mollie_customer_id, $subscription->mollie_id);
            $subscription->update(['ends_at' => $subscription->paid_until]);
        }
        $effectiveDate = $subscription->ends_at;
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

        $tierName = config('oasis.tiers.'.$user->currentMembershipTier().'.name', 'Oasis');

        // Cancel immediately
        $this->gateway->cancel($user->mollie_customer_id, $subscription->mollie_id);
        $subscription->update(['status' => 'canceled', 'ends_at' => now()]);

        // Refund paid invoices per § 357 Abs. 1 BGB
        $this->refundSubscriptionInvoices($subscription);

        // Send confirmation email
        Mail::to($user->email)->send(new MembershipWithdrawnMail($user, $tierName));
        Mail::to(config('oasis.mail.operator'))->send(new MembershipWithdrawnMail($user, $tierName, operatorCopy: true));
    }

    /**
     * Refund all paid invoices for a withdrawn subscription (§ 357 Abs. 1 BGB).
     */
    public function refundSubscriptionInvoices(Subscription $subscription): void
    {
        foreach (MembershipPayment::where('subscription_id', $subscription->id)->where('status', 'paid')->where('refunded', false)->get() as $payment) {
            try {
                $this->gateway->refund($payment->mollie_payment_id, $payment->amount);
                $payment->update(['refunded' => true]);
            } catch (\Throwable $e) {
                Log::error('Refund failed for subscription withdrawal.', ['exception_class' => $e::class]);
                Mail::raw('Die automatische Erstattung nach einem Widerruf ist fehlgeschlagen (Mollie-Zahlung '.$payment->mollie_payment_id
                    .'). Bitte innerhalb von 14 Tagen manuell im Mollie-Dashboard erstatten.',
                    fn ($message) => $message->to(config('oasis.mail.operator'))->subject('Oasis: Erstattung manuell nachholen'));
            }
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
}
