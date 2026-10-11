<?php

use App\Enums\Role;
use App\Models\GiftMembership;
use App\Models\User;
use Laravel\Cashier\Subscription;

test('retention prune command skips deletion by default when config switch is disabled', function () {
    config()->set('oasis.retention.auto_delete_expired', false);

    // Expired 40 days ago
    $user = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->subDays(40),
    ]);

    $this->artisan('oasis:prune-expired-accounts')
        ->expectsOutputToContain('Automatic account pruning is disabled')
        ->assertSuccessful();

    expect(User::find($user->id))->not->toBeNull();
});

test('retention prune command deletes expired accounts past retention days when enabled', function () {
    config()->set('oasis.retention.auto_delete_expired', true);
    config()->set('oasis.retention_days', 30);

    // User A: expired 45 days ago -> should be pruned
    $userA = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->subDays(45),
    ]);

    // User B: expired 10 days ago (still in 30-day retention window) -> should be kept
    $userB = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->subDays(10),
    ]);

    // User C: active trial -> should be kept
    $userC = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->addDays(15),
    ]);

    // User D: expired trial but active subscription -> should be kept
    $userD = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->subDays(45),
    ]);
    Subscription::create([
        'user_id' => $userD->id,
        'type' => 'oase',
        'stripe_id' => 'sub_userD',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase',
    ]);

    // User E: expired trial but active gift -> should be kept
    $userE = User::factory()->create([
        'role' => Role::Member,
        'trial_ends_at' => now()->subDays(45),
    ]);
    GiftMembership::factory()->redeemed($userE)->create();

    $this->artisan('oasis:prune-expired-accounts')
        ->expectsOutputToContain('Pruned 1 expired accounts')
        ->assertSuccessful();

    expect(User::find($userA->id))->toBeNull()
        ->and(User::find($userB->id))->not->toBeNull()
        ->and(User::find($userC->id))->not->toBeNull()
        ->and(User::find($userD->id))->not->toBeNull()
        ->and(User::find($userE->id))->not->toBeNull();
});
