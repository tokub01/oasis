<?php

namespace App\Http\Controllers\Membership;

use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom WebhookController handling Stripe events for Laravel Cashier,
 * founding member allocation, invoice monitoring, and gift voucher fulfillment.
 */
class StripeWebhookController extends WebhookController
{
    /**
     * Handle incoming Stripe webhook with mandatory secret and signature verification.
     */
    public function handleWebhook(Request $request): Response
    {
        $secret = config('cashier.webhook.secret');

        if (empty($secret)) {
            abort(403, 'Stripe webhook secret is not configured.');
        }

        $signature = $request->header('Stripe-Signature');
        if (empty($signature)) {
            abort(403, 'Missing Stripe signature header.');
        }

        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $signature,
                $secret,
                (int) config('cashier.webhook.tolerance', 300)
            );
        } catch (SignatureVerificationException $e) {
            abort(403, 'Invalid Stripe webhook signature.');
        }

        return parent::handleWebhook($request);
    }

    /**
     * Handle customer.subscription.created.
     */
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $customer = $payload['data']['object']['customer'] ?? null;
        if ($customer) {
            $user = Cashier::findBillable($customer);
            if ($user) {
                app(StripeMembershipService::class)->assignFoundingMemberIfEligible($user);
            }
        }

        return $response;
    }

    /**
     * Handle customer.subscription.updated.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer.subscription.deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Handle invoice.payment_succeeded.
     */
    public function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $response = parent::handleInvoicePaymentSucceeded($payload);

        $customer = $payload['data']['object']['customer'] ?? null;
        if ($customer) {
            $user = Cashier::findBillable($customer);
            if ($user) {
                app(StripeMembershipService::class)->assignFoundingMemberIfEligible($user);
            }
        }

        return $response;
    }

    /**
     * Handle invoice.payment_failed.
     */
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        Log::warning('Stripe invoice payment failed.');

        return $this->successMethod();
    }

    /**
     * Fulfill only paid gift sessions; delayed payment is handled by the async success event.
     */
    public function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];

        if (($metadata['type'] ?? null) === 'gift_membership'
            && ($session['payment_status'] ?? null) === 'paid'
            && is_string($session['id'] ?? null) && $session['id'] !== '') {
            app(StripeMembershipService::class)->fulfillGiftPurchase($metadata, $session['id']);
        }

        return $this->successMethod();
    }

    /**
     * Delayed bank payments finish after Checkout has already completed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCheckoutSessionAsyncPaymentSucceeded(array $payload): Response
    {
        return $this->handleCheckoutSessionCompleted($payload);
    }

    /**
     * No voucher is issued for failed delayed payments; do not log purchaser metadata.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCheckoutSessionAsyncPaymentFailed(array $payload): Response
    {
        if (($payload['data']['object']['metadata']['type'] ?? null) === 'gift_membership') {
            Log::warning('Stripe gift payment failed.');
        }

        return $this->successMethod();
    }
}
