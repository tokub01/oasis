<?php

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetPaginatedSubscriptionPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\Http\Requests\GetSubscriptionRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\MollieFixtures as Billing;

test('mollie sync repairs missed gift payment notifications without duplicating vouchers', function () {
    $intent = Billing::intent(User::factory()->create());
    Billing::fake(Billing::resource($intent));
    $this->artisan('oasis:mollie-sync')->expectsOutputToContain('Mollie-Abgleich abgeschlossen.')->assertSuccessful();
    $this->artisan('oasis:mollie-sync')->assertSuccessful();
    expect(GiftMembership::count())->toBe(1);
    Mollie::assertSentCount(1);
});

test('mollie sync fails without exposing API response bodies', function () {
    Billing::intent(User::factory()->create());
    Mollie::fake([GetPaymentRequest::class => MockResponse::notFound('secret@example.tld')]);
    $this->artisan('oasis:mollie-sync')->expectsOutputToContain('Zahlungsabgleich fehlgeschlagen')->assertFailed();
    Mollie::assertSentCount(1);
});

test('mollie sync discovers a missing recurring payment and reconciles provider cancellation', function () {
    $this->freezeTime();
    $user = User::factory()->create(['mollie_customer_id' => 'cst_sync']);
    $sub = Subscription::create(['user_id' => $user->id, 'mollie_id' => 'sub_sync',
        'type' => 'oase', 'status' => 'active', 'amount' => 500, 'rhythm' => 'monat', 'paid_until' => now()]);
    $payment = ['resource' => 'payment', 'id' => 'tr_missed', 'customerId' => 'cst_sync', 'subscriptionId' => 'sub_sync',
        'amount' => ['currency' => 'EUR', 'value' => '5.00'], 'status' => 'paid', 'paidAt' => now()->toIso8601String(), 'createdAt' => now()->toIso8601String()];
    Mollie::fake([
        GetPaginatedSubscriptionPaymentsRequest::class => MockResponse::ok(['count' => 1, '_embedded' => ['payments' => [$payment]], '_links' => ['self' => ['href' => 'https://api.mollie.com/v2/customers/cst_sync/subscriptions/sub_sync/payments']]]),
        GetSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_sync', 'status' => 'canceled']),
        GetPaymentRequest::class => MockResponse::ok($payment),
    ]);
    $this->artisan('oasis:mollie-sync')->assertSuccessful();
    expect($sub->fresh()->paid_until->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
    expect($sub->fresh()->ends_at->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
    expect($user->fresh()->hasActiveMembership())->toBeTrue();
    Mollie::assertSentCount(3);
});
