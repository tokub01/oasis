<?php

use App\Models\User;
use App\Services\Membership\GroupTrialService;

test('group trial service starts simultaneous 30-day trial for multiple members', function () {
    $service = new GroupTrialService;

    $userA = User::factory()->create([
        'trial_ends_at' => now()->addDays(5),
    ]);
    $userB = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $userC = User::factory()->create([
        'trial_ends_at' => null,
    ]);

    expect($userB->hasActiveTrial())->toBeFalse();

    $service->startGroupTrial([$userA, $userB, $userC]);

    $userA->refresh();
    $userB->refresh();
    $userC->refresh();

    expect($userA->hasActiveTrial())->toBeTrue()
        ->and($userB->hasActiveTrial())->toBeTrue()
        ->and($userC->hasActiveTrial())->toBeTrue()
        ->and((int) round(now()->diffInDays($userA->trial_ends_at)))->toBe(30)
        ->and((int) round(now()->diffInDays($userB->trial_ends_at)))->toBe(30)
        ->and((int) round(now()->diffInDays($userC->trial_ends_at)))->toBe(30);
});

test('user model can start group trial directly', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDays(2),
    ]);

    expect($user->hasActiveTrial())->toBeFalse();

    $user->startGroupTrial(45);
    $user->refresh();

    expect($user->hasActiveTrial())->toBeTrue()
        ->and((int) round(now()->diffInDays($user->trial_ends_at)))->toBe(45);
});

test('group trial service calculates remaining group formation days and auto-start threshold', function () {
    $service = new GroupTrialService;

    config()->set('oasis.group_start_days', 7);

    // Group created just now -> 7 days remaining, should not auto start
    $now = now();
    expect($service->daysRemainingToStartGroup($now))->toBe(7)
        ->and($service->shouldAutoStartGroup($now))->toBeFalse();

    // Group created 3 days ago -> 4 days remaining
    $threeDaysAgo = now()->subDays(3);
    expect($service->daysRemainingToStartGroup($threeDaysAgo))->toBe(4)
        ->and($service->shouldAutoStartGroup($threeDaysAgo))->toBeFalse();

    // Group created 7 days ago -> 0 days remaining, auto-start is true
    $sevenDaysAgo = now()->subDays(7);
    expect($service->daysRemainingToStartGroup($sevenDaysAgo))->toBe(0)
        ->and($service->shouldAutoStartGroup($sevenDaysAgo))->toBeTrue();

    // Group created 10 days ago -> 0 days remaining, auto-start is true
    $tenDaysAgo = now()->subDays(10);
    expect($service->daysRemainingToStartGroup($tenDaysAgo))->toBe(0)
        ->and($service->shouldAutoStartGroup($tenDaysAgo))->toBeTrue();
});
