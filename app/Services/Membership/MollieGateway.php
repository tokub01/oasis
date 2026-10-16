<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\Http\Data\Money;
use Mollie\Api\Http\Request;
use Mollie\Api\Http\Requests\CancelSubscriptionRequest;
use Mollie\Api\Http\Requests\CreateCustomerRequest;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\CreateSubscriptionRequest;
use Mollie\Api\Http\Requests\GetPaginatedSubscriptionPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\Http\Requests\GetSubscriptionRequest;
use Mollie\Api\Http\Requests\UpdateSubscriptionRequest;
use Mollie\Api\Resources\Payment;
use Mollie\Laravel\Facades\Mollie;

/** Typed SDK boundary for native Mollie payments, mandates and subscriptions. */
class MollieGateway
{
    /** Send using stable idempotency keys so retries after a lost response cannot duplicate charges. */
    private function send(Request $request, ?string $key = null): mixed
    {
        if ($key !== null) {
            Mollie::setIdempotencyKey($key);
        }

        return Mollie::send($request);
    }

    /** Create a reusable customer without exposing account data in logs. */
    public function customer(User $user): string
    {
        return $this->send(new CreateCustomerRequest(name: $user->display_name, email: $user->email), 'oasis-customer-'.$user->id)->id;
    }

    /** Create a hosted checkout; first sequence establishes a mandate for subsequent automatic charges. */
    public function payment(MembershipPayment $intent, string $customer, ?string $method): Payment
    {
        return $this->send(new CreatePaymentRequest(
            description: $intent->kind === 'mandate' ? 'Oasis – Zahlungsmittel ändern' : 'Oasis '.$intent->tier,
            amount: new Money('EUR', number_format($intent->amount / 100, 2, '.', '')),
            redirectUrl: route('app.membership'), webhookUrl: route('mollie.webhook'),
            method: $method, metadata: ['intent_id' => (string) $intent->id],
            sequenceType: $intent->kind === 'gift' ? 'oneoff' : 'first', customerId: $customer,
        ), 'oasis-payment-'.$intent->id);
    }

    /** Always reload a webhook's resource over the authenticated API. */
    public function getPayment(string $id): Payment
    {
        return $this->send(new GetPaymentRequest($id));
    }

    /** Start recurring billing after the already paid first period, using its mandate. */
    public function subscribe(Subscription $subscription, string $customer, string $mandate, string $key): string
    {
        return $this->send(new CreateSubscriptionRequest(
            customerId: $customer, amount: new Money('EUR', number_format($subscription->amount / 100, 2, '.', '')),
            interval: $subscription->rhythm === 'jahr' ? '12 months' : '1 month',
            description: 'Oasis '.$subscription->type, startDate: $subscription->paid_until,
            mandateId: $mandate, webhookUrl: route('mollie.webhook'),
        ), $key)->id;
    }

    /** Stop future charges; prepaid access is handled in the local domain service. */
    public function cancel(string $customer, string $subscription): void
    {
        $remote = $this->send(new GetSubscriptionRequest($customer, $subscription));
        if ($remote->status !== 'canceled' && $remote->status !== 'completed') {
            $this->send(new CancelSubscriptionRequest($customer, $subscription));
        }
    }

    /** Retrieve the recurring agreement state for reconciliation. */
    public function subscriptionStatus(string $customer, string $subscription): string
    {
        return $this->send(new GetSubscriptionRequest($customer, $subscription))->status;
    }

    /** Return every recurring payment, oldest first, so missed callbacks can be replayed in billing order.
     * @return list<string>
     */
    public function subscriptionPayments(string $customer, string $subscription): array
    {
        $page = $this->send(new GetPaginatedSubscriptionPaymentsRequest($customer, $subscription, limit: 250));
        $payments = iterator_to_array($page->getAutoIterator());
        usort($payments, fn ($a, $b) => strcmp($a->createdAt, $b->createdAt));

        return array_map(fn ($payment) => $payment->id, $payments);
    }

    /** Replace the mandate after a successful hosted first payment. */
    public function changeMandate(string $customer, string $subscription, string $mandate, string $key): void
    {
        $this->send(new UpdateSubscriptionRequest($customer, $subscription, mandateId: $mandate), $key);
    }

    /** Refund to the original payment method (§ 357 BGB); a stable key makes retries safe. */
    public function refund(string $payment, int $amount): void
    {
        $this->send(CreatePaymentRefundRequest::for($payment, new Money('EUR', number_format($amount / 100, 2, '.', '')), 'Oasis Erstattung'), 'oasis-refund-'.$payment);
    }
}
