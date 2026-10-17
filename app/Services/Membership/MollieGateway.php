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
use Mollie\Api\Http\Requests\GetCustomerRequest;
use Mollie\Api\Http\Requests\GetPaginatedCustomerPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaginatedSubscriptionPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\Http\Requests\GetSubscriptionRequest;
use Mollie\Api\Http\Requests\UpdateSubscriptionRequest;
use Mollie\Api\Resources\Customer;
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

    /** Webhook target, or none where Mollie cannot reach the app (see `oasis.payment.webhooks`). */
    private function webhookUrl(): ?string
    {
        return config('oasis.payment.webhooks') ? route('mollie.webhook') : null;
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
            redirectUrl: route('app.membership'), webhookUrl: $this->webhookUrl(),
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
            mandateId: $mandate, webhookUrl: $this->webhookUrl(),
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

    /**
     * One payment as Mollie knows it, for the admin payment search (read only).
     *
     * @return array{id: string, status: string, amount: string, currency: string, refunded: string|null, method: string|null, description: string|null, created: string|null, paid: string|null, customer: string|null, subscription: string|null, sequence: string|null, dashboard: string|null}
     */
    public function lookupPayment(string $id): array
    {
        return $this->summary($this->send(new GetPaymentRequest($id)));
    }

    /**
     * The newest payments of one Mollie customer, newest first (admin payment search, read only).
     *
     * @return list<array{id: string, status: string, amount: string, currency: string, refunded: string|null, method: string|null, description: string|null, created: string|null, paid: string|null, customer: string|null, subscription: string|null, sequence: string|null, dashboard: string|null}>
     */
    public function customerPayments(string $customer, int $limit = 50): array
    {
        $payments = [];
        foreach ($this->send(new GetPaginatedCustomerPaymentsRequest($customer, limit: $limit)) as $payment) {
            $payments[] = $this->summary($payment);
        }

        return $payments;
    }

    /**
     * Name, e-mail and dashboard link of one Mollie customer (admin payment search, read only).
     *
     * @return array{id: string, name: string|null, email: string|null, created: string|null, dashboard: string|null}
     */
    public function lookupCustomer(string $id): array
    {
        /** @var Customer $customer */
        $customer = $this->send(new GetCustomerRequest($id));

        return ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email,
            'created' => $customer->createdAt, 'dashboard' => self::dashboardLink($customer->_links->dashboard->href ?? null)];
    }

    /**
     * The fields the admin search shows; the dashboard link comes from Mollie itself.
     *
     * @return array{id: string, status: string, amount: string, currency: string, refunded: string|null, method: string|null, description: string|null, created: string|null, paid: string|null, customer: string|null, subscription: string|null, sequence: string|null, dashboard: string|null}
     */
    private function summary(Payment $payment): array
    {
        return [
            'id' => $payment->id, 'status' => $payment->status,
            'amount' => $payment->amount->value, 'currency' => $payment->amount->currency,
            'refunded' => $payment->amountRefunded->value ?? null, 'method' => $payment->method,
            'description' => $payment->description, 'created' => $payment->createdAt, 'paid' => $payment->paidAt,
            'customer' => $payment->customerId, 'subscription' => $payment->subscriptionId,
            'sequence' => $payment->sequenceType, 'dashboard' => self::dashboardLink($payment->_links->dashboard->href ?? null),
        ];
    }

    /** Only Mollie's own https links become clickable in the admin area. */
    private static function dashboardLink(?string $href): ?string
    {
        return $href !== null && preg_match('#^https://([a-z0-9-]+\.)*mollie\.com/#', $href) === 1 ? $href : null;
    }
}
