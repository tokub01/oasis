<?php

use App\Enums\Role;
use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'participate'])->group(function () {
        Route::get('/test-participate-web', fn () => response('OK'));
        Route::post('/test-participate-web', fn () => response('OK'));
    });

    Route::middleware(['auth', 'participate'])->group(function () {
        Route::post('/api/test-participate-json', fn () => response()->json(['status' => 'OK']));
    });
});

test('user with active trial can participate and passes gate and middleware', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(15),
    ]);

    expect($user->hasActiveTrial())->toBeTrue()
        ->and($user->canParticipate())->toBeTrue();

    expect(Gate::forUser($user)->allows('participate'))->toBeTrue();

    $this->actingAs($user)
        ->get('/test-participate-web')
        ->assertOk()
        ->assertSee('OK');

    $this->actingAs($user)
        ->postJson('/api/test-participate-json')
        ->assertOk()
        ->assertJson(['status' => 'OK']);
});

test('user with expired trial and no subscription cannot participate', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->hasActiveTrial())->toBeFalse()
        ->and($user->canParticipate())->toBeFalse();

    expect(Gate::forUser($user)->allows('participate'))->toBeFalse();

    // Web request is redirected to app.membership with status flash
    $this->actingAs($user)
        ->get('/test-participate-web')
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Deine Testphase ist abgelaufen. Bitte schließe eine Mitgliedschaft ab, um aktiv mitzumachen.');

    // JSON request returns 403 with German message
    $this->actingAs($user)
        ->postJson('/api/test-participate-json')
        ->assertStatus(403)
        ->assertJson([
            'message' => 'Deine Testphase ist abgelaufen. Bitte schließe eine Mitgliedschaft ab, um mitzumachen.',
        ]);
});

test('staff users can always participate regardless of trial', function () {
    $admin = User::factory()->create([
        'role' => Role::Admin,
        'trial_ends_at' => now()->subDays(10),
    ]);

    $moderator = User::factory()->create([
        'role' => Role::Moderator,
        'trial_ends_at' => null,
    ]);

    expect($admin->canParticipate())->toBeTrue()
        ->and($moderator->canParticipate())->toBeTrue();

    expect(Gate::forUser($admin)->allows('participate'))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('participate'))->toBeTrue();

    $this->actingAs($admin)
        ->get('/test-participate-web')
        ->assertOk();
});

test('user with active gift membership can participate after trial end', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    GiftMembership::factory()->redeemed($user, months: 12)->create([
        'tier' => 'oase',
    ]);

    expect($user->hasActiveGiftMembership())->toBeTrue()
        ->and($user->canParticipate())->toBeTrue()
        ->and($user->currentMembershipTier())->toBe('oase');

    $this->actingAs($user)
        ->get('/test-participate-web')
        ->assertOk();
});

test('user with expired gift membership cannot participate', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDays(35),
    ]);

    $gift = GiftMembership::factory()->redeemed($user)->create([
        'expires_at' => now()->subDay(),
    ]);

    expect($gift->isActive())->toBeFalse()
        ->and($user->hasActiveGiftMembership())->toBeFalse()
        ->and($user->canParticipate())->toBeFalse();

    $this->actingAs($user)
        ->get('/test-participate-web')
        ->assertRedirect(route('app.membership'));
});

test('read-only accounts with expired trial can still export and delete their account', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDays(5),
    ]);

    // Export remains accessible
    $this->actingAs($user)
        ->get(route('account.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    // Delete confirmation remains accessible (password confirmation route)
    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk();
});

test('current membership tier resolves from subscription item or returns null', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->currentMembershipTier())->toBeNull();

    $sub = Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'mollie_id' => 'sub_default_item',
        'status' => 'active', 'paid_until' => now()->addMonth(),

    ]);

    $user->load('subscriptions');
    expect($user->currentMembershipTier())->toBe('oase')
        ->and($user->subscription('default')?->id)->toBe($sub->id);
});
