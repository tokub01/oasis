<?php

use App\Mail\Membership\MembershipCancelledMail;
use App\Models\GiftMembership;
use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Fake\SequenceMockResponse;
use Mollie\Api\Http\Requests\CancelSubscriptionRequest;
use Mollie\Api\Http\Requests\CreateCustomerRequest;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\CreateSubscriptionRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\Http\Requests\GetSubscriptionRequest;
use Mollie\Api\Http\Requests\UpdateSubscriptionRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\MollieFixtures as Billing;

test('hosted checkout sends configured prices and a recurring first sequence', function (string $tier, string $rhythm, ?int $custom, string $method, string $expected, ?string $providerMethod) {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create(['mollie_customer_id' => 'cst_checkout']);
    Mollie::fake([CreatePaymentRequest::class => MockResponse::created(['resource' => 'payment', 'id' => 'tr_checkout', 'status' => 'open',
        '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/test']]])]);
    $this->actingAs($user)->post(route('oasis.mitglied.checkout'), ['stufe' => $tier, 'rhythmus' => $rhythm,
        'betrag' => $custom, 'zahlart' => $method])->assertRedirect('https://www.mollie.com/checkout/test');
    $intent = MembershipPayment::sole();
    expect($intent->status)->toBe('open');
    expect($user->fresh()->hasActiveMembership())->toBeFalse();
    Mollie::assertSent(fn ($request) => $request->payload()->get('amount')['value'] === $expected
        && $request->payload()->get('sequenceType') === 'first'
        && $request->payload()->get('method') === $providerMethod
        && $request->headers()->get('Idempotency-Key') === 'oasis-payment-'.$intent->id);
})->with([
    ['solidar', 'monat', null, 'karte', '3.00', 'creditcard'],
    ['solidar', 'jahr', null, 'paypal', '30.00', 'paypal'],
    ['oase', 'monat', null, 'sepa', '5.00', null],
    ['oase', 'jahr', null, 'karte', '50.00', 'creditcard'],
    ['maezen', 'monat', null, 'karte', '12.00', 'creditcard'],
    ['maezen', 'jahr', 35, 'paypal', '350.00', 'paypal'],
]);

test('the webhook URL is sent unless webhooks are switched off for a local setup', function (bool $webhooks) {
    config()->set(['oasis.checkout_enabled' => true, 'oasis.payment.webhooks' => $webhooks]);
    $user = User::factory()->create(['mollie_customer_id' => 'cst_checkout']);
    Mollie::fake([CreatePaymentRequest::class => MockResponse::created(['resource' => 'payment', 'id' => 'tr_local',
        '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/local']]])]);
    $this->actingAs($user)->post(route('oasis.mitglied.checkout'), ['stufe' => 'oase', 'rhythmus' => 'monat', 'zahlart' => 'karte'])
        ->assertRedirect('https://www.mollie.com/checkout/local');
    Mollie::assertSent(fn ($request) => $request->payload()->get('webhookUrl') === ($webhooks ? route('mollie.webhook') : null));
})->with([
    'production' => [true],
    'local' => [false],
]);

test('the membership page preselects card and never asks for bank details itself', function () {
    config()->set('oasis.checkout_enabled', true);
    $this->actingAs(User::factory()->create())->get(route('oasis.mitglied'))->assertOk()
        ->assertSee('name="zahlart" value="karte" data-label="Kreditkarte" checked', false)
        ->assertDontSee('name="iban"', false)->assertDontSee('name="inhaber"', false);
});

test('new customers are persisted and gifts use oneoff payments', function () {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create();
    Mollie::fake([
        CreateCustomerRequest::class => MockResponse::created(['resource' => 'customer', 'id' => 'cst_new']),
        CreatePaymentRequest::class => MockResponse::created(['resource' => 'payment', 'id' => 'tr_gift',
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/gift']]]),
    ]);
    $this->actingAs($user)->post(route('app.membership.gift.checkout'), ['tier' => 'solidar'])->assertRedirect('https://www.mollie.com/checkout/gift');
    expect($user->fresh()->mollie_customer_id)->toBe('cst_new');
    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreatePaymentRequest
        && $request->payload()->get('amount')['value'] === '30.00' && $request->payload()->get('sequenceType') === 'oneoff');
});

test('a webhook recovers a payment whose creation response was lost', function () {
    $intent = Billing::intent(User::factory()->create(), 'gift');
    $resource = Billing::resource($intent, 'paid', ['metadata' => ['intent_id' => (string) $intent->id]]);
    $intent->update(['mollie_payment_id' => null]);
    Billing::fake($resource);
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_test123'])->assertOk();
    expect($intent->fresh()->mollie_payment_id)->toBe('tr_test123');
    expect(GiftMembership::count())->toBe(1);
    Mollie::assertSentCount(1);
});

test('a second paid checkout is refunded instead of starting another agreement', function () {
    $user = User::factory()->create();
    $intent = Billing::intent($user, 'membership');
    Subscription::create(['user_id' => $user->id, 'type' => 'oase', 'mollie_id' => 'sub_existing',
        'status' => 'active', 'paid_until' => now()->addMonth(), 'amount' => 500]);
    Mollie::fake([
        GetPaymentRequest::class => MockResponse::ok(Billing::resource($intent)),
        CreatePaymentRefundRequest::class => MockResponse::created(['resource' => 'refund', 'id' => 're_duplicate']),
    ]);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    expect($intent->fresh()->refunded)->toBeTrue();
    expect(Subscription::count())->toBe(1);
    Mollie::assertSentCount(2);
});

test('mandate replacement updates the agreement and refunds the verification payment only once', function () {
    $user = User::factory()->create();
    $intent = Billing::intent($user, 'mandate', attributes: ['amount' => 100]);
    $sub = Subscription::create(['user_id' => $user->id, 'type' => 'oase', 'mollie_id' => 'sub_existing',
        'status' => 'active', 'paid_until' => now()->addMonth(), 'amount' => 500]);
    $intent->update(['subscription_id' => $sub->id]);
    Mollie::fake([
        GetPaymentRequest::class => new SequenceMockResponse(MockResponse::ok(Billing::resource($intent)), MockResponse::ok(Billing::resource($intent))),
        UpdateSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_existing']),
        CreatePaymentRefundRequest::class => MockResponse::created(['resource' => 'refund', 'id' => 're_verify']),
    ]);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    expect($intent->fresh()->refunded)->toBeTrue();
    Mollie::assertSentCount(4);
});

test('only the payer can read a paid invoice and its billing snapshot is escaped', function () {
    $payer = User::factory()->create();
    $intent = Billing::intent($payer, attributes: ['status' => 'paid', 'paid_at' => now(), 'invoice_data' => ['customer_name' => '<script>alert(1)</script>']]);
    $this->actingAs(User::factory()->create())->get(route('app.membership.invoice', $intent))->assertNotFound();
    $this->actingAs($payer)->get(route('app.membership.invoice', $intent))->assertOk()
        ->assertSee('Rechnung OAS-'.$intent->id)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $intent->update(['status' => 'pending']);
    $this->get(route('app.membership.invoice', $intent))->assertNotFound();
});

test('subscription creation retries keep their idempotency key after a database rollback', function () {
    $intent = Billing::intent(User::factory()->create(), 'membership');
    Mollie::fake([
        GetPaymentRequest::class => new SequenceMockResponse(MockResponse::ok(Billing::resource($intent)), MockResponse::ok(Billing::resource($intent))),
        CreateSubscriptionRequest::class => new SequenceMockResponse(
            MockResponse::unprocessableEntity('try again'), MockResponse::created(['resource' => 'subscription', 'id' => 'sub_recovered'])),
    ]);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertStatus(503);
    expect(Subscription::count())->toBe(0);
    $this->postJson(route('mollie.webhook'), ['id' => $intent->mollie_payment_id])->assertOk();
    expect(Subscription::count())->toBe(1);
    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreateSubscriptionRequest
        && $request->headers()->get('Idempotency-Key') === 'oasis-subscription-payment-'.$intent->id);
    Mollie::assertSentCount(4);
});

test('yearly cancellation retains the paid year and repeated cancellation sends no second API cancellation', function () {
    $this->freezeTime();
    Mail::fake();
    $user = User::factory()->create(['mollie_customer_id' => 'cst_year']);
    $sub = Subscription::create(['user_id' => $user->id, 'mollie_id' => 'sub_year', 'status' => 'active',
        'type' => 'maezen', 'amount' => 35000, 'rhythm' => 'jahr', 'paid_until' => now()->addMonths(11)]);
    Mollie::fake([
        GetSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_year', 'status' => 'active']),
        CancelSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_year', 'status' => 'canceled']),
    ]);
    $this->actingAs($user)->post(route('app.membership.cancel'))->assertRedirect();
    $this->post(route('app.membership.cancel'))->assertRedirect();
    expect($sub->fresh()->ends_at->toDateString())->toBe(now()->addMonths(11)->toDateString());
    expect($user->fresh()->hasActiveMembership())->toBeTrue();
    Mail::assertSent(MembershipCancelledMail::class);
    Mollie::assertSentCount(2);
});

test('failed withdrawal refund alerts the operator and can be retried without refunding twice', function () {
    Mail::fake();
    $user = User::factory()->create(['mollie_customer_id' => 'cst_withdraw']);
    $sub = Subscription::create(['user_id' => $user->id, 'mollie_id' => 'sub_withdraw', 'status' => 'active',
        'type' => 'oase', 'amount' => 500, 'rhythm' => 'monat', 'paid_until' => now()->addMonth()]);
    $intent = Billing::intent($user, 'membership', attributes: ['subscription_id' => $sub->id, 'status' => 'paid']);
    Mollie::fake([
        GetSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_withdraw', 'status' => 'active']),
        CancelSubscriptionRequest::class => MockResponse::ok(['resource' => 'subscription', 'id' => 'sub_withdraw', 'status' => 'canceled']),
        CreatePaymentRefundRequest::class => new SequenceMockResponse(MockResponse::unprocessableEntity(), MockResponse::created(['resource' => 'refund', 'id' => 're_retry'])),
    ]);
    $service = app(MollieMembershipService::class);
    $service->withdrawSubscription($user->fresh());
    expect($user->fresh()->hasActiveMembership())->toBeFalse();
    expect($intent->fresh()->refunded)->toBeFalse();
    $service->refundSubscriptionInvoices($sub->fresh());
    $service->refundSubscriptionInvoices($sub->fresh());
    expect($intent->fresh()->refunded)->toBeTrue();
    Mail::assertSent(Mailable::class, fn ($mail) => $mail->hasTo(config('oasis.mail.operator')));
    Mollie::assertSentCount(4);
});

test('mandate verification payments are excluded from invoices and billing dates use Berlin time', function () {
    $user = User::factory()->create();
    $verification = Billing::intent($user, 'mandate', 'tr_verify123', ['status' => 'paid', 'amount' => 100,
        'paid_at' => '2026-10-15 23:30:00', 'refunded' => true]);
    $contribution = Billing::intent($user, 'membership', 'tr_contribution123', ['status' => 'paid', 'paid_at' => '2026-10-15 23:30:00']);

    $this->actingAs($user)->get(route('app.membership.portal'))->assertOk()
        ->assertSee('16.10.2026')->assertSee('Rechnung OAS-'.$contribution->id)
        ->assertDontSee('Rechnung OAS-'.$verification->id);
    $this->get(route('app.membership.invoice', $verification->id))->assertNotFound();
});
