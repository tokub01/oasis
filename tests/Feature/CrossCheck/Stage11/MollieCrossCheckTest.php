<?php

use App\Livewire\Membership\Cancel;
use App\Livewire\Membership\Checkout;
use App\Livewire\Membership\Overview;
use App\Livewire\Membership\Withdraw;
use App\Mail\Membership\MembershipCancelledMail;
use App\Mail\Membership\MembershipWithdrawnMail;
use App\Models\GiftMembership;
use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Membership\MembershipStats;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Fake\SequenceMockResponse;
use Mollie\Api\Http\Requests\CancelSubscriptionRequest;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\CreateSubscriptionRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\Http\Requests\GetSubscriptionRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\MollieFixtures as Billing;
use Tests\Support\NetworkActions;

test('forged webhook payloads never activate membership or override local ledger when API disagrees', function () {
    $user = User::factory()->create([
        'mollie_customer_id' => 'cst_honest',
        'trial_ends_at' => now()->subDay(),
    ]);

    $intent = Billing::intent($user, 'membership', 'tr_honest123', [
        'tier' => 'solidar',
        'rhythm' => 'monat',
        'amount' => 300,
    ]);

    // 1. Caller sends forged body claiming paid + Maezen, but Mollie API says payment is still open.
    Billing::fake(Billing::resource($intent, 'open'));

    $this->postJson(route('mollie.webhook'), [
        'id' => 'tr_honest123',
        'status' => 'paid',
        'amount' => ['currency' => 'EUR', 'value' => '99.00'],
        'metadata' => ['tier' => 'maezen', 'rhythm' => 'jahr', 'amount' => 99000],
    ])->assertOk();

    expect($user->fresh()->canParticipate())->toBeFalse()
        ->and(Subscription::count())->toBe(0)
        ->and($intent->fresh()->status)->toBe('open')
        ->and($intent->fresh()->processed_at)->toBeNull();

    // 2. Mollie API returns paid, but with tampered metadata in API response: local intent tier/amount must win.
    Mollie::fake([
        GetPaymentRequest::class => MockResponse::ok(Billing::resource($intent, 'paid', [
            'metadata' => ['tier' => 'maezen', 'rhythm' => 'jahr', 'amount' => 99000],
        ])),
        CreateSubscriptionRequest::class => MockResponse::created([
            'resource' => 'subscription',
            'id' => 'sub_honest123',
            'status' => 'active',
        ]),
    ]);

    $this->postJson(route('mollie.webhook'), [
        'id' => 'tr_honest123',
        'status' => 'paid',
    ])->assertOk();

    $subscription = Subscription::sole();
    expect($subscription->type)->toBe('solidar')
        ->and($subscription->rhythm)->toBe('monat')
        ->and($subscription->amount)->toBe(300)
        ->and($user->fresh()->canParticipate())->toBeTrue();

    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreateSubscriptionRequest
        && $request->payload()->get('amount')['value'] === '3.00'
        && $request->payload()->get('interval') === '1 month');
});

test('duplicate webhooks for initial and recurring subscription payments are strictly idempotent', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'mollie_customer_id' => 'cst_idem',
        'trial_ends_at' => now()->subDay(),
    ]);

    $firstIntent = Billing::intent($user, 'membership', 'tr_firstidem1', [
        'tier' => 'oase',
        'rhythm' => 'monat',
        'amount' => 500,
    ]);

    $firstResource = Billing::resource($firstIntent, 'paid');

    Mollie::fake([
        GetPaymentRequest::class => new SequenceMockResponse(
            MockResponse::ok($firstResource),
            MockResponse::ok($firstResource),
        ),
        CreateSubscriptionRequest::class => MockResponse::created([
            'resource' => 'subscription',
            'id' => 'sub_idem123',
            'status' => 'active',
        ]),
    ]);

    // Deliver the initial payment webhook twice.
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_firstidem1'])->assertOk();
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_firstidem1'])->assertOk();

    $subscription = Subscription::sole();
    $expectedFirstPeriodEnd = now()->addMonthNoOverflow()->toDateTimeString();

    expect(Subscription::count())->toBe(1)
        ->and($subscription->paid_until->toDateTimeString())->toBe($expectedFirstPeriodEnd);

    // Exactly 2 GetPaymentRequests + 1 CreateSubscriptionRequest = 3 requests total.
    Mollie::assertSentCount(3);

    // Now deliver a recurring charge webhook twice for the same subscription.
    $recurringResource = [
        'resource' => 'payment',
        'id' => 'tr_recidem123',
        'customerId' => $user->fresh()->mollie_customer_id,
        'subscriptionId' => 'sub_idem123',
        'sequenceType' => 'recurring',
        'status' => 'paid',
        'paidAt' => now()->toIso8601String(),
        'createdAt' => now()->toIso8601String(),
        'amount' => ['currency' => 'EUR', 'value' => '5.00'],
    ];

    Mollie::fake([
        GetPaymentRequest::class => new SequenceMockResponse(
            MockResponse::ok($recurringResource),
            MockResponse::ok($recurringResource),
        ),
    ]);

    $this->postJson(route('mollie.webhook'), ['id' => 'tr_recidem123'])->assertOk();
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_recidem123'])->assertOk();

    $expectedSecondPeriodEnd = now()->addMonthNoOverflow()->addMonthNoOverflow()->toDateTimeString();

    expect($subscription->fresh()->paid_until->toDateTimeString())->toBe($expectedSecondPeriodEnd)
        ->and(MembershipPayment::where('mollie_payment_id', 'tr_recidem123')->count())->toBe(1);
});

test('gift voucher is only issued after API-confirmed paid status and not on checkout or unpaid states', function () {
    config()->set('oasis.checkout_enabled', true);

    $buyer = User::factory()->create(['mollie_customer_id' => 'cst_giftbuyer']);
    $recipient = User::factory()->create(['trial_ends_at' => now()->subDay()]);

    Mollie::fake([
        CreatePaymentRequest::class => MockResponse::created([
            'resource' => 'payment',
            'id' => 'tr_giftflow12',
            'status' => 'open',
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/gift-flow']],
        ]),
    ]);

    Livewire::actingAs($buyer)
        ->test(Overview::class)
        ->set('tier', 'oase')
        ->call('buyGift')
        ->assertRedirect('https://www.mollie.com/checkout/gift-flow');

    $intent = MembershipPayment::sole();
    expect(GiftMembership::count())->toBe(0);

    // Unpaid states (open, pending, failed, canceled) must never create a voucher even if webhook body claims paid.
    foreach (['open', 'pending', 'failed', 'canceled'] as $unpaidStatus) {
        Billing::fake(Billing::resource($intent, $unpaidStatus, ['paidAt' => null]));

        $this->postJson(route('mollie.webhook'), [
            'id' => 'tr_giftflow12',
            'status' => 'paid',
        ])->assertOk();

        expect(GiftMembership::count())->toBe(0);
    }

    // Paid API response without paidAt timestamp must also not issue a voucher.
    Billing::fake(Billing::resource($intent, 'paid', ['paidAt' => null]));
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_giftflow12'])->assertOk();
    expect(GiftMembership::count())->toBe(0);

    // Paid API response with paidAt issues exactly one voucher across duplicate webhooks.
    $paidResource = Billing::resource($intent, 'paid');
    Billing::fake($paidResource, $paidResource);

    $this->postJson(route('mollie.webhook'), ['id' => 'tr_giftflow12'])->assertOk();
    $this->postJson(route('mollie.webhook'), ['id' => 'tr_giftflow12'])->assertOk();

    $voucher = GiftMembership::sole();
    expect($voucher->tier)->toBe('oase')
        ->and($voucher->amount)->toBe(5000)
        ->and($voucher->months)->toBe(12);

    // Recipient redeems voucher and transitions from read-only to full participation + founding member.
    expect($recipient->canParticipate())->toBeFalse();

    Livewire::actingAs($recipient)
        ->test(Overview::class)
        ->set('code', $voucher->code)
        ->call('redeem')
        ->assertHasNoErrors();

    expect($recipient->fresh()->canParticipate())->toBeTrue()
        ->and($recipient->fresh()->isFoundingMember())->toBeTrue();
});

test('custom Maezen amount is validated, persisted for monthly and yearly rhythms, and reflected in MRR', function () {
    $this->freezeTime();
    config()->set('oasis.checkout_enabled', true);

    $monthlyUser = User::factory()->create([
        'mollie_customer_id' => 'cst_maezenm1',
        'trial_ends_at' => now()->subDay(),
    ]);

    // Below minimum (12 €) is rejected.
    Livewire::actingAs($monthlyUser)
        ->test(Checkout::class)
        ->set('betrag', '11')
        ->set('stufe', 'maezen')
        ->set('rhythmus', 'monat')
        ->set('zahlart', 'karte')
        ->call('checkout')
        ->assertHasErrors(['betrag']);

    // Monthly custom Maezen (25 € -> 25.00 EUR first payment + 25.00 EUR recurring 1 month).
    Mollie::fake([
        CreatePaymentRequest::class => MockResponse::created([
            'resource' => 'payment',
            'id' => 'tr_maezenm123',
            'status' => 'open',
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/maezen-m']],
        ]),
    ]);

    Livewire::actingAs($monthlyUser)
        ->test(Checkout::class)
        ->set('betrag', '25')
        ->set('stufe', 'maezen')
        ->set('rhythmus', 'monat')
        ->set('zahlart', 'karte')
        ->call('checkout')
        ->assertRedirect('https://www.mollie.com/checkout/maezen-m');

    $monthlyIntent = MembershipPayment::where('user_id', $monthlyUser->id)->sole();
    expect($monthlyIntent->amount)->toBe(2500)
        ->and($monthlyIntent->rhythm)->toBe('monat');

    Mollie::fake([
        GetPaymentRequest::class => MockResponse::ok(Billing::resource($monthlyIntent, 'paid')),
        CreateSubscriptionRequest::class => MockResponse::created([
            'resource' => 'subscription',
            'id' => 'sub_maezen_m',
            'status' => 'active',
        ]),
    ]);

    $this->postJson(route('mollie.webhook'), ['id' => 'tr_maezenm123'])->assertOk();

    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreateSubscriptionRequest
        && $request->payload()->get('amount')['value'] === '25.00'
        && $request->payload()->get('interval') === '1 month');

    // Yearly custom Maezen (25 € * 10 months = 250.00 EUR / 25000 cents for 12 months).
    $yearlyUser = User::factory()->create([
        'mollie_customer_id' => 'cst_maezeny1',
        'trial_ends_at' => now()->subDay(),
    ]);

    Mollie::fake([
        CreatePaymentRequest::class => MockResponse::created([
            'resource' => 'payment',
            'id' => 'tr_maezeny123',
            'status' => 'open',
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/maezen-y']],
        ]),
    ]);

    Livewire::actingAs($yearlyUser)
        ->test(Checkout::class)
        ->set('betrag', '25')
        ->set('stufe', 'maezen')
        ->set('rhythmus', 'jahr')
        ->set('zahlart', 'paypal')
        ->call('checkout')
        ->assertRedirect('https://www.mollie.com/checkout/maezen-y');

    $yearlyIntent = MembershipPayment::where('user_id', $yearlyUser->id)->sole();
    expect($yearlyIntent->amount)->toBe(25000)
        ->and($yearlyIntent->rhythm)->toBe('jahr');

    Mollie::fake([
        GetPaymentRequest::class => MockResponse::ok(Billing::resource($yearlyIntent, 'paid')),
        CreateSubscriptionRequest::class => MockResponse::created([
            'resource' => 'subscription',
            'id' => 'sub_maezen_y',
            'status' => 'active',
        ]),
    ]);

    $this->postJson(route('mollie.webhook'), ['id' => 'tr_maezeny123'])->assertOk();

    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreateSubscriptionRequest
        && $request->payload()->get('amount')['value'] === '250.00'
        && $request->payload()->get('interval') === '12 months');

    // Verify MembershipStats MRR in euros: 25.00 (monthly) + round(250.00 / 12, 2) (20.83) = 45.83 EUR.
    $mrr = app(MembershipStats::class)->monthlyRecurringRevenue();
    expect($mrr)->toBe(round(25 + (250 / 12), 2));
});

test('cancellation keeps participation during grace period and enforces read-only mode after subscription ends', function () {
    Mail::fake();

    $user = User::factory()->create([
        'mollie_customer_id' => 'cst_cancel_flow',
        'trial_ends_at' => now()->subDays(10),
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_cancel_flow',
        'status' => 'active',
        'amount' => 500,
        'rhythm' => 'monat',
        'paid_until' => now()->addDays(15),
    ]);

    Mollie::fake([
        GetSubscriptionRequest::class => MockResponse::ok([
            'resource' => 'subscription',
            'id' => 'sub_cancel_flow',
            'status' => 'active',
        ]),
        CancelSubscriptionRequest::class => MockResponse::ok([
            'resource' => 'subscription',
            'id' => 'sub_cancel_flow',
            'status' => 'canceled',
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(Cancel::class)
        ->call('cancel')
        ->assertRedirect(route('app.membership'));

    $subscription->refresh();
    expect($subscription->canceled())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue()
        ->and($user->fresh()->canParticipate())->toBeTrue();

    Mail::assertSent(MembershipCancelledMail::class, 2);

    // During grace period, member can still publish a Tropfen.
    NetworkActions::publish([
        'body' => 'Ein Tropfen waehrend der bezahlten Restlaufzeit.',
    ], $user)->assertHasNoErrors();

    // Advance time past paid_until / ends_at: user enters read-only mode.
    $this->travel(16)->days();

    expect($subscription->fresh()->onGracePeriod())->toBeFalse()
        ->and($user->fresh()->canParticipate())->toBeFalse();

    // Read access and GDPR export remain available, but writing is blocked with 403.
    $this->actingAs($user)->get(route('app.membership'))->assertOk();
    $this->actingAs($user)->get(route('account.export'))->assertOk();
    NetworkActions::publish([
        'body' => 'Versuch nach Ablauf des Abos.',
    ], $user)
        ->assertForbidden();
});

test('14-day withdrawal revokes participation immediately, refunds paid invoices via Mollie API, and honors end-of-day boundary', function () {
    Mail::fake();

    // Created 14 days ago earlier in the same calendar day (e.g. 14.2 days ago): still within day 14 endOfDay (§ 188 Abs. 2 BGB).
    $this->travelTo(now()->startOfDay()->addHours(18));

    $user = User::factory()->create([
        'mollie_customer_id' => 'cst_withdraw1',
        'trial_ends_at' => now()->subDays(20),
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'maezen',
        'mollie_id' => 'sub_withdraw_flow',
        'status' => 'active',
        'amount' => 1800,
        'rhythm' => 'monat',
        'paid_until' => now()->addDays(16),
    ]);
    $subscription->forceFill(['created_at' => now()->subDays(14)->subHours(6)])->save();

    $payment = Billing::intent($user, 'membership', 'tr_withdraw12', [
        'subscription_id' => $subscription->id,
        'tier' => 'maezen',
        'rhythm' => 'monat',
        'amount' => 1800,
        'status' => 'paid',
        'paid_at' => now()->subDays(14)->subHours(6),
        'processed_at' => now()->subDays(14)->subHours(6),
    ]);

    Mollie::fake([
        GetSubscriptionRequest::class => MockResponse::ok([
            'resource' => 'subscription',
            'id' => 'sub_withdraw_flow',
            'status' => 'active',
        ]),
        CancelSubscriptionRequest::class => MockResponse::ok([
            'resource' => 'subscription',
            'id' => 'sub_withdraw_flow',
            'status' => 'canceled',
        ]),
        CreatePaymentRefundRequest::class => MockResponse::created([
            'resource' => 'refund',
            'id' => 're_withdraw_flow',
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(Withdraw::class)
        ->call('withdraw')
        ->assertRedirect(route('app.membership'));

    expect($subscription->fresh()->status)->toBe('canceled')
        ->and($user->fresh()->canParticipate())->toBeFalse()
        ->and($payment->fresh()->refunded)->toBeTrue();

    Mollie::assertSent(fn ($request) => $request->getRequest() instanceof CreatePaymentRefundRequest
        && $request->payload()->get('amount')['value'] === '18.00'
        && $request->headers()->get('Idempotency-Key') === 'oasis-refund-tr_withdraw12');

    Mail::assertSent(MembershipWithdrawnMail::class, 2);

    // Writing is immediately forbidden after withdrawal.
    NetworkActions::publish(['body' => 'Nach Widerruf nicht mehr erlaubt.'], $user)
        ->assertForbidden();
});

test('no Stripe or Cashier leftovers remain in composer, config, CSP, or privacy policy', function () {
    config()->set('oasis.checkout_enabled', true);

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->toHaveKey('mollie/laravel-mollie')
        ->not->toHaveKey('laravel/cashier')
        ->not->toHaveKey('stripe/stripe-php')
        ->and(config('oasis.payment.provider'))->toBe('Mollie')
        ->and(config('cashier'))->toBeNull()
        ->and(file_exists(config_path('cashier.php')))->toBeFalse();

    $checkoutResponse = $this->get(route('oasis.mitglied'))->assertOk();
    $csp = (string) $checkoutResponse->headers->get('Content-Security-Policy');

    expect($csp)->toContain("form-action 'self' https://www.mollie.com https://checkout.mollie.com")
        ->not->toContain('stripe.com');

    $this->get(route('oasis.datenschutz'))
        ->assertOk()
        ->assertSee('Mollie B.V.')
        ->assertDontSee('Stripe');
});
