<?php

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreateSubscriptionRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\MollieFixtures as Billing;

test('webhooks require a bounded Mollie payment identifier', function (array $payload) {
    Mollie::fake();
    $this->postJson(route('mollie.webhook'), $payload)->assertUnprocessable();
    Mollie::assertSentCount(0);
})->with([[[]], [['id' => '../../secret']], [['id' => ['tr_test']]]]);

test('unknown forged payment identifiers fail closed when the API rejects them', function () {
    Mollie::fake([GetPaymentRequest::class => MockResponse::notFound()]);
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_forged', 'status' => 'paid'])->assertStatus(503);
    expect(GiftMembership::count())->toBe(0);
    Mollie::assertSentCount(1);
});

test('pending payments do not issue vouchers even when the submitted body says paid', function () {
    $intent = Billing::intent(User::factory()->create());
    Billing::fake(Billing::resource($intent, 'pending'));
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id, 'status' => 'paid'])->assertOk();
    expect(GiftMembership::count())->toBe(0);
    Mollie::assertSentCount(1);
});

test('paid gift fulfillment uses the local order and is idempotent', function () {
    $intent = Billing::intent(User::factory()->create());
    $resource = Billing::resource($intent, 'paid', ['metadata' => ['tier' => 'maezen', 'amount' => 1]]);
    Billing::fake($resource, $resource);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    $code = GiftMembership::sole()->code;
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    expect(GiftMembership::sole())->tier->toBe('oase')->amount->toBe(5000)->code->toBe($code);
    expect($intent->fresh()->processed_at)->not->toBeNull();
    Mollie::assertSentCount(2);
});

test('mismatched API customer amount or currency never fulfills an order', function (array $override) {
    $intent = Billing::intent(User::factory()->create());
    Billing::fake(Billing::resource($intent, 'paid', $override));
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertStatus(503);
    expect(GiftMembership::count())->toBe(0);
    expect($intent->fresh()->processed_at)->toBeNull();
    Mollie::assertSentCount(1);
})->with([[['customerId' => 'cst_other']], [['amount' => ['currency' => 'EUR', 'value' => '1.00']]], [['amount' => ['currency' => 'USD', 'value' => '50.00']]]]);

test('a paid first payment starts recurring billing after the paid period and grants a founding seat', function () {
    $this->freezeTime();
    $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);
    $intent = Billing::intent($user, 'membership');
    Mollie::fake([
        GetPaymentRequest::class => MockResponse::ok(Billing::resource($intent)),
        CreateSubscriptionRequest::class => MockResponse::created(['resource' => 'subscription', 'id' => 'sub_test123', 'status' => 'active']),
    ]);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    expect($user->fresh()->canParticipate())->toBeTrue();
    expect($user->fresh()->isFoundingMember())->toBeTrue();
    expect(Subscription::sole()->paid_until->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreateSubscriptionRequest
        && $request->payload()->get('startDate') === now()->addMonthNoOverflow()->toDateString());
});

test('failed recurring payments do not extend access', function () {
    $user = User::factory()->create(['mollie_customer_id' => 'cst_recurring', 'trial_ends_at' => now()->subDay()]);
    $sub = Subscription::create(['user_id' => $user->id, 'type' => 'oase', 'status' => 'active', 'amount' => 500,
        'rhythm' => 'monat', 'mollie_id' => 'sub_test123', 'paid_until' => now()->subMinute()]);
    Mollie::fake([GetPaymentRequest::class => MockResponse::ok(['resource' => 'payment', 'id' => 'tr_recurring',
        'customerId' => 'cst_recurring', 'subscriptionId' => 'sub_test123', 'status' => 'failed', 'amount' => ['currency' => 'EUR', 'value' => '5.00']])]);
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_recurring'])->assertOk();
    expect($user->fresh()->canParticipate())->toBeFalse();
    expect($sub->fresh()->paid_until->isPast())->toBeTrue();
    Mollie::assertSentCount(1);
});
