<?php

use App\Models\GiftMembership;
use App\Models\User;
use App\Services\Membership\StripeMembershipService;

test('generates voucher codes in the expected format', function () {
    $code = GiftMembership::generateCode();

    expect($code)->toMatch('/^OASIS-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
});

test('user can redeem a valid gift voucher code', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->canParticipate())->toBeFalse();

    $voucher = GiftMembership::factory()->create([
        'tier' => 'oase',
        'months' => 12,
        'redeemed_by_user_id' => null,
        'redeemed_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('app.membership.redeem'), [
            'code' => $voucher->code,
        ])
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status');

    $voucher->refresh();
    expect($voucher->isRedeemed())->toBeTrue()
        ->and($voucher->redeemed_by_user_id)->toBe($user->id)
        ->and($voucher->expires_at)->not->toBeNull()
        ->and($voucher->isActive())->toBeTrue();

    $user->refresh();
    expect($user->hasActiveGiftMembership())->toBeTrue()
        ->and($user->canParticipate())->toBeTrue()
        ->and($user->isFoundingMember())->toBeTrue();
});

test('cannot redeem an already redeemed gift voucher code', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $voucher = GiftMembership::factory()->redeemed($user1)->create();

    $this->actingAs($user2)
        ->post(route('app.membership.redeem'), [
            'code' => $voucher->code,
        ])
        ->assertRedirect(route('app.membership'))
        ->assertSessionHasErrors('code');
});

test('rejects non-existent gift voucher code with German error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('app.membership.redeem'), [
            'code' => 'OASIS-INVALID-CODE',
        ])
        ->assertRedirect(route('app.membership'))
        ->assertSessionHasErrors('code');
});

test('purchasing a gift membership initiates checkout session', function () {
    $user = User::factory()->create();

    $serviceMock = Mockery::mock(StripeMembershipService::class);
    $serviceMock->shouldReceive('createGiftCheckout')
        ->once()
        ->with(Mockery::on(fn ($u) => $u->id === $user->id), 'oase')
        ->andReturn('https://checkout.stripe.com/pay/cs_test_gift');

    $this->app->instance(StripeMembershipService::class, $serviceMock);

    $this->actingAs($user)
        ->post(route('app.membership.gift.checkout'), [
            'tier' => 'oase',
        ])
        ->assertRedirect('https://checkout.stripe.com/pay/cs_test_gift');
});

test('gift membership relations resolve purchaser and redeemed by users', function () {
    $purchaser = User::factory()->create();
    $recipient = User::factory()->create();

    $voucher = GiftMembership::factory()->redeemed($recipient)->create([
        'purchaser_user_id' => $purchaser->id,
    ]);

    expect($voucher->purchaser->id)->toBe($purchaser->id)
        ->and($voucher->redeemedBy->id)->toBe($recipient->id)
        ->and($purchaser->purchasedGiftMemberships->first()->id)->toBe($voucher->id)
        ->and($recipient->giftMemberships->first()->id)->toBe($voucher->id);
});
