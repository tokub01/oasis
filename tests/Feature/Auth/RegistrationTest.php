<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/* Exercises Fortify's real registration endpoint and the Oasis account/consent rules. */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    RateLimiter::clear('registration|127.0.0.1');
});

function oasisRegistration(array $overrides = []): array
{
    return array_replace([
        'display_name' => 'Mira Sol', 'username' => 'Mira_Sol', 'email' => 'MIRA@example.tld',
        'birth_date' => '1990-06-15', 'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password', 'terms' => '1',
    ], $overrides);
}

it('registers a normalized unverified account with consent and a configured trial', function () {
    $this->travelTo(now()->startOfSecond());
    config(['oasis.trial_days' => 47]);
    $this->post(route('register'), oasisRegistration())->assertRedirect('/app');
    $user = User::sole();
    expect($user->username)->toBe('mira_sol');
    expect($user->email)->toBe('mira@example.tld');
    expect($user->display_name)->toBe('Mira Sol');
    expect($user->birth_date->format('Y-m-d'))->toBe('1990-06-15');
    expect($user->email_verified_at)->toBeNull();
    expect($user->terms_accepted_at->equalTo(now()))->toBeTrue();
    expect($user->trial_ends_at->equalTo(now()->addDays(47)))->toBeTrue();
    expect(Hash::check('a-long-password', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires sixteen completed years on the Berlin signup date', function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(22, 30));
    // UTC October 11 is already October 12 in Berlin.
    $this->post(route('register'), oasisRegistration(['birth_date' => '2010-10-13']))
        ->assertSessionHasErrors(['birth_date' => 'Du musst mindestens 16 Jahre alt sein, um Oasis zu nutzen.']);
    $this->assertDatabaseCount('users', 0);
    $this->post(route('register'), oasisRegistration(['birth_date' => '2010-10-12']))->assertRedirect('/app');
});

it('rejects invalid registration fields without creating an account', function ($input, $field) {
    $this->post(route('register'), oasisRegistration($input))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
})->with([
    'reserved admin' => [['username' => 'ADMIN'], 'username'],
    'reserved oasis' => [['username' => 'oasis'], 'username'],
    'reserved help' => [['username' => 'hilfe'], 'username'],
    'short username' => [['username' => 'ab'], 'username'],
    'long username' => [['username' => str_repeat('a', 31)], 'username'],
    'invalid username' => [['username' => 'mira-sol'], 'username'],
    'long display name' => [['display_name' => str_repeat('a', 51)], 'display_name'],
    'malformed birth date' => [['birth_date' => 'not-a-date'], 'birth_date'],
    'nonexistent date' => [['birth_date' => '2000-02-30'], 'birth_date'],
    'short password' => [['password' => '123456789', 'password_confirmation' => '123456789'], 'password'],
    'missing confirmation' => [['password_confirmation' => 'different'], 'password'],
    'missing terms' => [['terms' => null], 'terms'],
    'declined terms' => [['terms' => '0'], 'terms'],
]);

it('rejects duplicate usernames after lowercasing and duplicate email addresses', function () {
    User::factory()->create(['username' => 'mira_sol', 'email' => 'mira@example.tld']);
    $this->post(route('register'), oasisRegistration())->assertSessionHasErrors(['username', 'email']);
    $this->assertDatabaseCount('users', 1);
});

it('throttles the seventh registration submission per IP even when validation fails', function () {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('register'), [])->assertSessionHasErrors('username');
    }
    $this->post(route('register'), [])->assertStatus(429);
});

it('uses German password validation messages', function () {
    $this->post(route('register'), oasisRegistration(['password' => 'short', 'password_confirmation' => 'short']))
        ->assertSessionHasErrors(['password' => 'Das Passwort muss mindestens 10 Zeichen lang sein.']);
});

it('accepts exactly ten password characters in the development policy', function () {
    $this->post(route('register'), oasisRegistration([
        'password' => '1234567890', 'password_confirmation' => '1234567890',
    ]))->assertRedirect('/app');
});
