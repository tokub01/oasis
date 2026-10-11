<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/* Optional TOTP setup, confirmation, challenge and single-use recovery-code checks. */
beforeEach(function () {
    $this->withoutVite();
});

it('requires password confirmation and a valid TOTP before enabling two factor login', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect(route('password.confirm'));
    expect($user->fresh()->two_factor_secret)->toBeNull();
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post(route('two-factor.enable'))->assertRedirect();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
    $this->post(route('two-factor.confirm'), ['code' => 'invalid'])
        ->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $this->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect();
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
    $this->delete(route('two-factor.disable'))->assertRedirect();
    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('challenges login and consumes a recovery code only once', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $recovery = 'one-use-recovery';
    $user = User::factory()->create([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([$recovery])),
        'two_factor_confirmed_at' => now(),
    ]);
    $credentials = ['login' => $user->username, 'password' => 'password'];
    $this->post(route('login'), $credentials)->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post(route('two-factor.login'), ['code' => 'invalid'])->assertSessionHasErrors('code');
    $this->post(route('two-factor.login'), ['recovery_code' => $recovery])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->recoveryCodes())->not->toContain($recovery);
    $this->post(route('logout'));
    Auth::forgetGuards();
    $this->post(route('login'), $credentials)->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login'), ['recovery_code' => $recovery])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
    $this->post(route('two-factor.login'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
});
