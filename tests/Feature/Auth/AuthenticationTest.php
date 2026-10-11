<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/* Real sign-in/out, reset-token and signed-email-verification flows through Fortify. */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('keeps the contracted German auth URLs', function () {
    expect(route('login', absolute: false))->toBe('/anmelden');
    expect(route('register', absolute: false))->toBe('/testen');
    expect(route('password.request', absolute: false))->toBe('/passwort-vergessen');
    expect(route('verification.notice', absolute: false))->toBe('/email/bestaetigen');
});

it('signs in with email case normalization and signs out', function () {
    $user = User::factory()->create(['email' => 'mira@example.tld']);
    $this->post(route('login'), ['login' => 'MIRA@example.tld', 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('signs in with the username instead of the email address', function () {
    $user = User::factory()->create(['username' => 'mira']);
    $this->post(route('login'), ['login' => 'Mira', 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
});

it('rehashes passwords at sign-in when the configured cost changes', function () {
    $user = User::factory()->create(['password' => Hash::make('password', ['rounds' => 4])]);
    config(['hashing.bcrypt.rounds' => 6]);
    Hash::forgetDrivers();
    expect(Hash::needsRehash($user->password))->toBeTrue();
    $this->post(route('login'), ['login' => $user->username, 'password' => 'password'])->assertRedirect('/app');
    expect(Hash::needsRehash($user->fresh()->password))->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials and throttles five attempts per login and IP', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login'), ['login' => 'missing@example.tld', 'password' => 'wrong'])
            ->assertSessionHasErrors('login');
    }
    $this->post(route('login'), ['login' => 'MISSING@example.tld', 'password' => 'wrong'])->assertStatus(429);
    $this->post(route('login'), ['login' => 'other@example.tld', 'password' => 'wrong'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('requires authentication and email verification to enter the app', function () {
    $this->get(route('app.home'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('app.home'))
        ->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create())->get(route('app.home'))->assertOk();
});

it('verifies the account using its signed link and rejects an unsigned link', function () {
    $user = User::factory()->unverified()->create();
    $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
    $this->actingAs($user)->get(route('verification.verify', $parameters))->assertForbidden();
    $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters))
        ->assertRedirect('/app?verified=1');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('sends a reset notification and resets the password only with the correct token', function () {
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });
    $input = ['email' => $user->email, 'token' => 'wrong', 'password' => 'new-long-password',
        'password_confirmation' => 'new-long-password'];
    $this->post(route('password.update'), $input)->assertSessionHasErrors('email');
    $this->post(route('password.update'), array_replace($input, ['token' => $token]))
        ->assertRedirect(route('login'));
    expect(Hash::check('new-long-password', $user->fresh()->password))->toBeTrue();
});

it('does not distinguish known, unknown or recently requested addresses in reset responses', function () {
    $user = User::factory()->create();
    foreach ([$user->email, 'unknown@example.tld', $user->email] as $email) {
        $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.request'))->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
        $this->postJson(route('password.email'), ['email' => $email])->assertOk()
            ->assertExactJson(['message' => __('passwords.sent')]);
    }
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
    expect(__('passwords.user'))->toBe(__('passwords.sent'));
});
