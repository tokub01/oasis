<?php

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Fake\SequenceMockResponse;
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

test('mollie sync never asks again for payments that can no longer change', function () {
    $user = User::factory()->create();
    foreach (['expired', 'canceled', 'failed'] as $i => $status) {
        Billing::intent($user, 'membership', 'tr_final'.$i, ['status' => $status]);
    }
    Mollie::fake([GetPaymentRequest::class => MockResponse::ok([])]);

    $this->artisan('oasis:mollie-sync')->assertSuccessful();

    Mollie::assertSentCount(0);
});

test('mollie sync updates the status of an open payment and keeps the record of a deleted account current', function () {
    $open = Billing::intent(User::factory()->create(), 'membership', 'tr_open1');
    $orphan = Billing::intent(User::factory()->create(), 'membership', 'tr_orphan1');
    $resources = [Billing::resource($open, 'expired', ['paidAt' => null]), Billing::resource($orphan, 'paid')];
    $orphan->user->delete();
    Mollie::fake([GetPaymentRequest::class => new SequenceMockResponse(...array_map(fn ($resource) => MockResponse::ok($resource), $resources))]);

    $this->artisan('oasis:mollie-sync')->assertSuccessful();

    expect($open->fresh()->status)->toBe('expired')
        ->and($orphan->fresh()->user_id)->toBeNull()
        ->and($orphan->fresh()->status)->toBe('paid')
        ->and($orphan->fresh()->processed_at)->not->toBeNull()
        ->and(GiftMembership::count())->toBe(0);
    Mollie::assertSentCount(2);
    // Both are settled now: the next run asks Mollie for neither.
    $this->artisan('oasis:mollie-sync')->assertSuccessful();
    Mollie::assertSentCount(2);
});
