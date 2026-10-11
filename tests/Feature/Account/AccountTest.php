<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/* Privacy endpoints and profile/password settings, including recent-password enforcement. */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('downloads only the authenticated users allowlisted data without secrets', function () {
    $owner = User::factory()->create(['two_factor_secret' => 'private-secret',
        'two_factor_recovery_codes' => 'private-codes']);
    $other = User::factory()->create();
    $response = $this->actingAs($owner)->get(route('account.export'))
        ->assertOk()->assertDownload('oasis-konto.json')->assertHeader('Content-Type', 'application/json');
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    expect($data['email'])->toBe($owner->email)->not->toBe($other->email);
    expect(array_keys($data))->toBe(['id', 'display_name', 'username', 'email', 'birth_date',
        'email_verified_at', 'terms_accepted_at', 'trial_ends_at', 'created_at', 'updated_at']);
    expect($response->streamedContent())->not->toContain('private-secret', 'private-codes', $other->username);
});

it('requires authentication for export and deletion', function () {
    $this->get(route('account.export'))->assertRedirect(route('login'));
    $this->delete(route('account.destroy'))->assertRedirect(route('login'));
});

it('keeps export available before email verification', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('account.export'))->assertOk();
});

it('requires a recent password confirmation before deleting', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->delete(route('account.destroy'))->assertRedirect(route('password.confirm'));
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->withSession(['auth.password_confirmed_at' => now()->subSeconds(config('auth.password_timeout') + 1)->timestamp])
        ->delete(route('account.destroy'))->assertRedirect(route('password.confirm'));
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('confirms the password then deletes only the current account and invalidates its session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-reset-token']);
    $this->actingAs($user)->post(route('password.confirm'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    $this->post(route('password.confirm'), ['password' => 'password'])->assertRedirect();
    $this->delete(route('account.destroy'))->assertRedirect(route('oasis.home'))->assertSessionHas('status');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('users', ['id' => $other->id]);
    $this->get(route('app.home'))->assertRedirect(route('login'));
});

it('updates the display name and reverifies an email change without changing consent or birth date', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('user-profile-information.update'), [
        'display_name' => 'New Name', 'email' => 'NEW@example.tld', 'birth_date' => '2020-01-01',
        'username' => 'admin', 'terms_accepted_at' => null,
    ])->assertRedirect();
    $updated = $user->fresh();
    expect($updated->email)->toBe('new@example.tld');
    expect($updated->display_name)->toBe('New Name');
    expect($updated->email_verified_at)->toBeNull();
    expect($updated->username)->toBe($user->username);
    expect($updated->birth_date->equalTo($user->birth_date))->toBeTrue();
    expect($updated->terms_accepted_at->equalTo($user->terms_accepted_at))->toBeTrue();
    Notification::assertSentTo($updated, VerifyEmail::class);
});

it('keeps verification for a display name change and rejects duplicate emails', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user)->put(route('user-profile-information.update'), [
        'display_name' => 'Changed', 'email' => $user->email,
    ])->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
    $this->put(route('user-profile-information.update'), ['display_name' => 'Changed', 'email' => $other->email])
        ->assertSessionHasErrors('email', errorBag: 'updateProfileInformation');
});

it('requires the current password and ten characters for password changes', function () {
    $user = User::factory()->create();
    $input = ['current_password' => 'wrong', 'password' => 'new-long-password',
        'password_confirmation' => 'new-long-password'];
    $this->actingAs($user)->put(route('user-password.update'), $input)
        ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');
    $this->put(route('user-password.update'), array_replace($input, [
        'current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short',
    ]))->assertSessionHasErrors('password', errorBag: 'updatePassword');
    $this->put(route('user-password.update'), array_replace($input, ['current_password' => 'password']))->assertRedirect();
    expect(Hash::check('new-long-password', $user->fresh()->password))->toBeTrue();
});
