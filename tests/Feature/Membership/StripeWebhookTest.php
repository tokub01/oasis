<?php

use App\Models\GiftMembership;
use App\Models\User;
use Laravel\Cashier\Subscription;

const TEST_WEBHOOK_SECRET = 'whsec_test_secret_for_signing_123';

function createStripeSignatureHeader(string $rawBody, string $secret, ?int $timestamp = null): string
{
    $ts = $timestamp ?? time();
    $signature = hash_hmac('sha256', "{$ts}.{$rawBody}", $secret);

    return "t={$ts},v1={$signature}";
}

test('webhook without configured secret is rejected with 403', function () {
    config()->set('cashier.webhook.secret', null);

    $this->postJson(route('cashier.webhook'), ['type' => 'invoice.payment_succeeded'])
        ->assertForbidden();
});

test('webhook without signature header is rejected with 403', function () {
    config()->set('cashier.webhook.secret', TEST_WEBHOOK_SECRET);

    $this->postJson(route('cashier.webhook'), ['type' => 'invoice.payment_succeeded'])
        ->assertForbidden();
});

test('webhook with invalid signature is rejected with 403', function () {
    config()->set('cashier.webhook.secret', TEST_WEBHOOK_SECRET);

    $this->postJson(route('cashier.webhook'), ['type' => 'invoice.payment_succeeded'], [
        'Stripe-Signature' => 't=12345,v1=invalidsignaturehere',
    ])->assertForbidden();
});

test('invoice.payment_succeeded signed webhook assigns founding member status', function () {
    config()->set('cashier.webhook.secret', TEST_WEBHOOK_SECRET);
    config()->set('oasis.founding.seats', 1000);

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_founding',
        'founding_member_at' => null,
    ]);

    expect($user->isFoundingMember())->toBeFalse();

    $payload = [
        'id' => 'evt_test_invoice_paid',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test123',
                'customer' => 'cus_test_founding',
                'subscription' => 'sub_test123',
            ],
        ],
    ];

    $json = json_encode($payload);
    $signature = createStripeSignatureHeader($json, TEST_WEBHOOK_SECRET);

    $this->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $json
    )->assertOk();

    $user->refresh();
    expect($user->isFoundingMember())->toBeTrue()
        ->and($user->founding_price_guarantee)->toBeTrue();
});

test('checkout.session.completed signed webhook fulfills gift membership voucher', function () {
    config()->set('cashier.webhook.secret', TEST_WEBHOOK_SECRET);

    $buyer = User::factory()->create();

    $payload = [
        'id' => 'evt_test_gift_checkout',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_gift_123',
                'payment_status' => 'paid',
                'metadata' => [
                    'type' => 'gift_membership',
                    'purchaser_id' => (string) $buyer->id,
                    'purchaser_email' => $buyer->email,
                    'tier' => 'oase',
                    'months' => '12',
                    'amount' => '5000',
                ],
            ],
        ],
    ];

    $json = json_encode($payload);
    $signature = createStripeSignatureHeader($json, TEST_WEBHOOK_SECRET);

    $this->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $json
    )->assertOk();

    $gift = GiftMembership::where('stripe_checkout_session_id', 'cs_test_gift_123')->first();
    expect($gift)->not->toBeNull()
        ->and($gift->tier)->toBe('oase')
        ->and($gift->months)->toBe(12)
        ->and($gift->purchaser_user_id)->toBe($buyer->id)
        ->and($gift->isRedeemed())->toBeFalse();
});

test('customer.subscription.deleted signed webhook cancels subscription', function () {
    config()->set('cashier.webhook.secret', TEST_WEBHOOK_SECRET);

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_cancel',
    ]);

    $sub = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'stripe_id' => 'sub_test_del',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase',
    ]);

    $payload = [
        'id' => 'evt_test_sub_deleted',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_del',
                'customer' => 'cus_test_cancel',
                'status' => 'canceled',
            ],
        ],
    ];

    $json = json_encode($payload);
    $signature = createStripeSignatureHeader($json, TEST_WEBHOOK_SECRET);

    $this->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $json
    )->assertOk();

    $sub->refresh();
    expect($sub->stripe_status)->toBe('canceled');
});
