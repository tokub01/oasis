<?php

use App\Livewire\Account\Settings;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/* Privacy endpoints and profile/password settings, including recent-password enforcement. */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('downloads only the authenticated users allowlisted data without secrets', function () {
    $owner = User::factory()->create(['two_factor_secret' => 'private-secret',
        'two_factor_recovery_codes' => 'private-codes']);
    $other = User::factory()->create();
    $owner->privacySetting()->create(['profile_audience' => 'following', 'mentions_audience' => 'nobody']);
    $response = $this->actingAs($owner)->get(route('account.export'))
        ->assertOk()->assertDownload('oasis-daten.zip')->assertHeader('Content-Type', 'application/zip');
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $json = $zip->getFromName('oasis-daten.json');
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    expect($data['konto']['email'])->toBe($owner->email)->not->toBe($other->email);
    expect(array_keys($data['konto']))->toBe(['id', 'display_name', 'username', 'email', 'birth_date',
        'email_verified_at', 'terms_accepted_at', 'trial_ends_at', 'created_at', 'updated_at']);
    expect($data['privatsphaere'])->toMatchArray(['profile_audience' => 'following', 'mentions_audience' => 'nobody', 'search_engines' => false]);
    expect(array_keys($data['privatsphaere']))->toBe(['profile_audience', 'default_post_visibility', 'follower_count_audience',
        'location_audience', 'oases_audience', 'mentions_audience', 'replies_audience', 'carry_audience', 'chat_invites_audience',
        'searchable', 'search_engines', 'login_alerts', 'verborgene_woerter']);
    expect($json)->not->toContain('private-secret', 'private-codes', $other->username);
});

it('requires authentication for export and settings', function () {
    $this->get(route('account.export'))->assertRedirect(route('login'));
    $this->get(route('app.settings'))->assertRedirect(route('login'));
});

it('keeps export available before email verification', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('account.export'))->assertOk();
});

it('requires a recent password confirmation before deleting via Livewire Settings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('confirm', true)->call('deleteAccount')
        ->assertRedirect(route('password.confirm'));
    $this->assertDatabaseHas('users', ['id' => $user->id]);

    $this->withSession(['auth.password_confirmed_at' => now()->subSeconds(config('auth.password_timeout') + 1)->timestamp]);
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('confirm', true)->call('deleteAccount')
        ->assertRedirect(route('password.confirm'));
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('confirms the password then deletes only the current account and invalidates its session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-reset-token']);
    $this->actingAs($user)->post(route('password.confirm'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    $this->post(route('password.confirm'), ['password' => 'password'])->assertRedirect();

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('confirm', true)->call('deleteAccount')
        ->assertRedirect(route('oasis.home'))
        ->assertSessionHas('status', 'Dein Konto wurde gelöscht.');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('users', ['id' => $other->id]);
    $this->get(route('app.home'))->assertRedirect(route('login'));
});

it('updates the display name and reverifies an email change without changing consent or birth date', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('display_name', 'New Name')
        ->set('email', 'NEW@example.tld')
        ->call('updateProfile')
        ->assertHasNoErrors()
        ->assertSee('Dein Profil ist gespeichert.');
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
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('display_name', 'Changed')
        ->set('email', $user->email)
        ->call('updateProfile')
        ->assertHasNoErrors();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('display_name', 'Changed')
        ->set('email', $other->email)
        ->call('updateProfile')
        ->assertHasErrors(['email']);
});

it('requires the current password and ten characters for password changes', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('current_password', 'wrong')
        ->set('password', 'new-long-password')
        ->set('password_confirmation', 'new-long-password')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('current_password', 'password')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('current_password', 'password')
        ->set('password', 'new-long-password')
        ->set('password_confirmation', 'new-long-password')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertSee('Dein Passwort ist geändert.');
    expect(Hash::check('new-long-password', $user->fresh()->password))->toBeTrue();
});

it('requires explicit deletion consent even with a recently confirmed password', function () {
    $user = User::factory()->create();
    $this->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::actingAs($user)->test(Settings::class)->call('deleteAccount')->assertHasErrors('confirm');

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
});
