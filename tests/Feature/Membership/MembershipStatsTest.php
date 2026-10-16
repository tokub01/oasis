<?php

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Membership\MembershipStats;

test('membership stats calculates trial metrics accurately', function () {
    $stats = new MembershipStats;

    $from = now()->subDays(30);
    $to = now();

    // 2 trials started in period
    User::factory()->create([
        'created_at' => now()->subDays(10),
        'trial_ends_at' => now()->addDays(20),
    ]);

    User::factory()->create([
        'created_at' => now()->subDays(5),
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($stats->trialsStarted($from, $to))->toBe(2)
        ->and($stats->trialsActive())->toBe(1);
});

test('membership stats calculates active members and MRR from local records', function () {
    $stats = new MembershipStats;

    // User A: Oase monthly (5 €)
    $userA = User::factory()->create();
    $subA = Subscription::create([
        'user_id' => $userA->id,
        'type' => 'oase',
        'mollie_id' => 'sub_A',
        'status' => 'active', 'paid_until' => now()->addMonth(),

    ]);

    // User B: Solidar yearly (30 € / year = 2.50 € MRR)
    $userB = User::factory()->create();
    $subB = Subscription::create([
        'user_id' => $userB->id,
        'type' => 'solidar',
        'mollie_id' => 'sub_B', 'rhythm' => 'jahr', 'amount' => 3000,
        'status' => 'active', 'paid_until' => now()->addMonth(),

    ]);

    // User C: Active gift membership (Oase)
    $userC = User::factory()->create();
    GiftMembership::factory()->redeemed($userC)->create([
        'tier' => 'oase',
    ]);

    $tiers = $stats->activeMembersByTier();
    expect($tiers['solidar'])->toBe(1)
        ->and($tiers['oase'])->toBe(2)
        ->and($tiers['maezen'])->toBe(0);

    expect($stats->activeMembersCount())->toBe(3);

    // MRR: 5.00 (Oase) + 2.50 (Solidar yearly) = 7.50 €
    expect($stats->monthlyRecurringRevenue())->toBe(7.5);
});

test('membership stats counts founding seats and gift vouchers', function () {
    config()->set('oasis.founding.seats', 100);

    $stats = new MembershipStats;

    User::factory()->count(4)->create([
        'founding_member_at' => now(),
        'founding_price_guarantee' => true,
    ]);

    expect($stats->foundingSeatsUsed())->toBe(4)
        ->and($stats->foundingSeatsRemaining())->toBe(96);

    $from = now()->subDays(7);
    $to = now();

    GiftMembership::factory()->count(3)->create([
        'created_at' => now()->subDays(2),
    ]);

    expect($stats->giftsSold($from, $to))->toBe(3);
});

test('membership stats calculates converted and expired trials', function () {
    $stats = new MembershipStats;

    $from = now()->subDays(30);
    $to = now();

    // Converted via subscription
    $userConvertedSub = User::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Subscription::create([
        'user_id' => $userConvertedSub->id,
        'type' => 'oase',
        'mollie_id' => 'sub_conv',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'created_at' => now()->subDays(10),
    ]);

    // Converted via redeemed gift
    $userConvertedGift = User::factory()->create(['trial_ends_at' => now()->addDays(10)]);
    GiftMembership::factory()->redeemed($userConvertedGift)->create([
        'redeemed_at' => now()->subDays(5),
    ]);

    expect($stats->trialsConverted($from, $to))->toBe(2);

    // Expired trial without conversion
    User::factory()->create([
        'trial_ends_at' => now()->subDays(3),
    ]);

    expect($stats->trialsExpired($from, $to))->toBe(1);
});

test('membership stats calculates custom maezen recurring revenue', function () {
    $stats = new MembershipStats;

    $userMaezenMonthly = User::factory()->create();
    $subM = Subscription::create([
        'user_id' => $userMaezenMonthly->id,
        'type' => 'maezen',
        'mollie_id' => 'sub_maezen_m', 'amount' => 2500,
        'status' => 'active', 'paid_until' => now()->addMonth(),

    ]);

    $userMaezenYearly = User::factory()->create();
    $subY = Subscription::create([
        'user_id' => $userMaezenYearly->id,
        'type' => 'maezen',
        'mollie_id' => 'sub_maezen_y', 'rhythm' => 'jahr', 'amount' => 120000,
        'status' => 'active', 'paid_until' => now()->addMonth(),

    ]);

    // 25.00 € (monthly) + 1200 € / 12 = 100.00 € (yearly) = 125.00 €
    expect($stats->monthlyRecurringRevenue())->toBe(125.0);
});

test('membership stats counts cancellations and redeemed gifts', function () {
    $stats = new MembershipStats;

    $from = now()->subDays(14);
    $to = now();

    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_cancel',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'ends_at' => now()->addDays(15),
        'updated_at' => now()->subDays(3),
    ]);

    expect($stats->cancellations($from, $to))->toBe(1);

    $recipient = User::factory()->create();
    GiftMembership::factory()->redeemed($recipient)->create([
        'redeemed_at' => now()->subDays(2),
    ]);

    expect($stats->giftsRedeemed($from, $to))->toBe(1);
});
