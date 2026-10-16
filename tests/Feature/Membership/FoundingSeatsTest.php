<?php

use App\Models\User;
use App\Services\Membership\MollieMembershipService;

test('awards founding member status and price guarantee within limit', function () {
    config()->set('oasis.founding.seats', 5);

    $service = app(MollieMembershipService::class);
    $user = User::factory()->create();

    expect($user->isFoundingMember())->toBeFalse();

    $assigned = $service->assignFoundingMemberIfEligible($user);

    expect($assigned)->toBeTrue();

    $user->refresh();
    expect($user->isFoundingMember())->toBeTrue()
        ->and($user->founding_price_guarantee)->toBeTrue()
        ->and($user->founding_member_at)->not->toBeNull();

    // Calling again returns false
    expect($service->assignFoundingMemberIfEligible($user))->toBeFalse();
});

test('does not award founding status once seat limit is reached', function () {
    config()->set('oasis.founding.seats', 2);

    $service = app(MollieMembershipService::class);

    // Create 2 existing founding members
    User::factory()->count(2)->create([
        'founding_member_at' => now(),
        'founding_price_guarantee' => true,
    ]);

    expect($service->remainingFoundingSeats())->toBe(0);

    $newUser = User::factory()->create();
    $assigned = $service->assignFoundingMemberIfEligible($newUser);

    expect($assigned)->toBeFalse();

    $newUser->refresh();
    expect($newUser->isFoundingMember())->toBeFalse();
});

test('correctly calculates remaining founding seats', function () {
    config()->set('oasis.founding.seats', 10);

    User::factory()->count(3)->create([
        'founding_member_at' => now(),
        'founding_price_guarantee' => true,
    ]);

    $service = app(MollieMembershipService::class);
    expect($service->remainingFoundingSeats())->toBe(7);
});
