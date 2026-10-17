<?php

use App\Enums\Role;
use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\NetworkFixtures as N;

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
        'mollie_id' => 'sub_userD',
        'status' => 'active', 'paid_until' => now()->addMonth(),

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

test('retention prune command keeps the download period after a paid membership and deletes like DeleteAccount', function () {
    config()->set('oasis.retention.auto_delete_expired', true);
    config()->set('oasis.retention_days', 30);
    Storage::fake(config('oasis.media_disk'));

    $recent = User::factory()->create(['role' => Role::Member, 'trial_ends_at' => now()->subYear()]);
    Subscription::create(['user_id' => $recent->id, 'type' => 'oase', 'status' => 'canceled', 'paid_until' => now()->subDays(10)]);

    $expired = User::factory()->create(['role' => Role::Member, 'trial_ends_at' => now()->subYear()]);
    Subscription::create(['user_id' => $expired->id, 'type' => 'oase', 'status' => 'canceled', 'paid_until' => now()->subDays(40)]);
    $post = N::post($expired);
    Storage::disk(config('oasis.media_disk'))->put('network/posts/'.$post->id.'/bild.jpg', 'x');

    $this->artisan('oasis:prune-expired-accounts')->expectsOutputToContain('Pruned 1 expired accounts')->assertSuccessful();

    expect(User::find($recent->id))->not->toBeNull()
        ->and(User::find($expired->id))->toBeNull();
    Storage::disk(config('oasis.media_disk'))->assertMissing('network/posts/'.$post->id.'/bild.jpg');
});

test('retention prune command is switched on by default, as AGB and privacy policy promise', function () {
    expect(config('oasis.retention.auto_delete_expired'))->toBeTrue();
});
