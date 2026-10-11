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
        $customer = $payload['data']['object']['customer'] ?? null;
        Log::warning("Stripe invoice payment failed for customer {$customer}.");

        return parent::handleInvoicePaymentFailed($payload);
    }

    /**
     * Handle checkout.session.completed for gift memberships.
     */
    public function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];

        if (($metadata['type'] ?? null) === 'gift_membership') {
            app(StripeMembershipService::class)->fulfillGiftPurchase($metadata, $session['id'] ?? null);
        }

        return $this->successMethod();
    }
}
