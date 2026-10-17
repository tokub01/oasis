<?php

/*
 * Lost second factor (docs/oasis-accounts.md, "Zwei-Faktor verloren"): password, e-mail link, a waiting period with
 * notice and cancel link, automatic cancel when the owner signs in with the second factor, then reset and sign-out.
 */

use App\Enums\Role;
use App\Mail\Account\TwoFactorResetCompleted;
use App\Mail\Account\TwoFactorResetLink;
use App\Mail\Account\TwoFactorResetScheduled;
use App\Models\TwoFactorReset;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

function twoFactorUser(array $attributes = []): User
{
    return User::factory()->create($attributes + ['email' => 'anna@example.tld', 'password' => 'a-long-password',
        'two_factor_secret' => encrypt('secret'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()]);
}

/** Request a reset and return the token from the mail. */
function requestReset(string $email = 'anna@example.tld', string $password = 'a-long-password'): ?string
{
    test()->post(route('two-factor.recovery.store'), ['email' => $email, 'password' => $password])
        ->assertRedirect(route('two-factor.recovery.create'))->assertSessionHas('status');
    $token = null;
    Mail::assertSent(TwoFactorResetLink::class, function (TwoFactorResetLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

it('answers the same for wrong input and sends the link only for an account with 2FA and the right password', function () {
    twoFactorUser();
    User::factory()->create(['email' => 'ohne@example.tld', 'password' => 'a-long-password']);

    foreach ([['niemand@example.tld', 'a-long-password'], ['anna@example.tld', 'falsch'], ['ohne@example.tld', 'a-long-password']] as [$email, $password]) {
        $this->post(route('two-factor.recovery.store'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('two-factor.recovery.create'))
            ->assertSessionHas('status', 'Wenn E-Mail-Adresse und Passwort zu einem Konto mit Zwei-Faktor-Anmeldung passen, bekommst du gleich eine E-Mail mit dem nächsten Schritt.');
    }
    Mail::assertNothingSent();

    $token = requestReset();
    expect(TwoFactorReset::sole()->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
});

it('waits 72 hours after the e-mail is confirmed, then switches 2FA off and signs out everywhere', function () {
    $user = twoFactorUser();
    $user->forceFill(['remember_token' => 'alter-token'])->save();
    DB::table('sessions')->insert(['id' => 'geraet', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    $token = requestReset();

    $this->get(route('two-factor.recovery.show', $token))->assertOk()->assertSee('Rücksetzung bestätigen');
    expect(TwoFactorReset::sole()->state())->toBe('requested');
    $this->post(route('two-factor.recovery.confirm', $token))->assertRedirect(route('two-factor.recovery.show', $token));
    Mail::assertSent(TwoFactorResetScheduled::class, fn ($mail) => $mail->hasTo('anna@example.tld') && $mail->token === $token);

    $this->travel(71)->hours();
    $this->post(route('two-factor.recovery.complete', $token))->assertRedirect(route('two-factor.recovery.show', $token));
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->get(route('two-factor.recovery.show', $token))->assertSee('Noch etwas Geduld');

    $this->travel(2)->hours();
    $this->post(route('two-factor.recovery.complete', $token))->assertRedirect(route('login'));
    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->remember_token)->not->toBe('alter-token')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    Mail::assertSent(TwoFactorResetCompleted::class);
    $this->get(route('two-factor.recovery.show', $token))->assertSee('Erledigt');
});

it('stops when the owner cancels or signs in with the second factor', function () {
    $user = twoFactorUser();
    $token = requestReset();
    $this->post(route('two-factor.recovery.confirm', $token));

    $this->post(route('two-factor.recovery.cancel', $token));
    $this->travel(73)->hours();
    $this->post(route('two-factor.recovery.complete', $token));
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();

    RateLimiter::clear('two-factor-recovery:'.sha1('anna@example.tld'));
    $second = requestReset();
    $this->post(route('two-factor.recovery.confirm', $second));
    event(new Login('web', $user->fresh(), false));
    $this->travel(73)->hours();
    $this->post(route('two-factor.recovery.complete', $second));
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(TwoFactorReset::whereNull('cancelled_at')->count())->toBe(0);
});

it('lets links expire and replaces an older request', function () {
    twoFactorUser();
    $first = requestReset();
    $this->travel(25)->hours();
    $this->get(route('two-factor.recovery.show', $first))->assertSee('Link nicht mehr gültig');
    $this->post(route('two-factor.recovery.confirm', $first));
    expect(TwoFactorReset::sole()->confirmed_at)->toBeNull();

    $this->get(route('two-factor.recovery.show', str_repeat('a', 48)))->assertOk()->assertSee('Link nicht mehr gültig');
    $this->post(route('two-factor.recovery.confirm', str_repeat('a', 48)))->assertNotFound();

    $this->post(route('two-factor.recovery.store'), ['email' => 'anna@example.tld', 'password' => 'a-long-password']);
    $this->post(route('two-factor.recovery.store'), ['email' => 'anna@example.tld', 'password' => 'a-long-password']);
    expect(TwoFactorReset::count())->toBe(3)
        ->and(TwoFactorReset::whereNull('cancelled_at')->count())->toBe(1);
});

it('limits requests per address and day and alerts the operator for staff accounts', function () {
    twoFactorUser(['role' => Role::Moderator]);
    foreach (range(1, 4) as $i) {
        $this->post(route('two-factor.recovery.store'), ['email' => 'anna@example.tld', 'password' => 'a-long-password']);
    }
    Mail::assertSent(TwoFactorResetLink::class, 3);

    $token = null;
    Mail::assertSent(TwoFactorResetLink::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    $this->post(route('two-factor.recovery.confirm', $token));
    Mail::assertSent(TwoFactorResetScheduled::class, fn ($mail) => $mail->hasTo(config('oasis.mail.operator')) && $mail->token === null);
});

it('is linked from the code entry and protected by the bot traps', function () {
    $this->withSession(['login.id' => twoFactorUser()->id])->get(route('two-factor.login'))
        ->assertOk()->assertSee(route('two-factor.recovery.create'), false);
    config(['oasis.bot_protection.time_trap' => true]);

    $this->post(route('two-factor.recovery.store'), ['email' => 'anna@example.tld', 'password' => 'a-long-password'])->assertSessionHasErrors('form');
    Mail::assertNothingSent();
});
