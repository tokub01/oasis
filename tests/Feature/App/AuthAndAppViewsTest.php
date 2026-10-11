<?php

/*
 * UI checks for stage 0: the auth pages render the agreed form fields, the marketing CTAs lead to the real
 * sign-up/sign-in, and the signed-in app shell and settings show what the account backend provides.
 */

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

describe('auth pages', function () {
    it('renders the sign-up form with the agreed field names', function () {
        $html = $this->get(route('register'))->assertOk()->getContent();

        foreach (['display_name', 'username', 'email', 'password', 'password_confirmation', 'birth_date', 'terms'] as $field) {
            expect($html)->toContain('name="'.$field.'"');
        }
        expect($html)->toContain('action="'.route('register').'"')->toContain('name="_token"');
    });

    it('renders the sign-in form with email-or-username login', function () {
        $this->get(route('login'))->assertOk()
            ->assertSee('name="login"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee(route('password.request'), false);
    });

    it('renders the forgot and reset password pages', function () {
        $this->get(route('password.request'))->assertOk()->assertSee('name="email"', false);
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'lena@example.tld']))->assertOk()
            ->assertSee('value="abc"', false)
            ->assertSee('lena@example.tld');
    });

    it('lets marketing pages lead to the real sign-up and sign-in', function () {
        $this->get(route('oasis.home'))->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    });
});

describe('app shell', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('app.home'))->assertRedirect(route('login'));
    });

    it('shows the home with the member name, navigation and trial banner', function () {
        $user = User::factory()->create(['display_name' => 'Lena Berger', 'trial_ends_at' => now()->addDays(12)]);

        $this->actingAs($user)->get(route('app.home'))->assertOk()
            ->assertSee('Hallo Lena Berger.')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Noch 12 Tage in deinem Test.')
            ->assertSee('noindex', false);
    });

    it('hides the trial banner once the trial is over', function () {
        $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($user)->get(route('app.home'))->assertOk()->assertDontSee('in deinem Test.');
    });
});

describe('settings', function () {
    it('shows every settings block with its endpoint', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.settings'))->assertOk()
            ->assertSee(route('user-profile-information.update'), false)
            ->assertSee(route('user-password.update'), false)
            ->assertSee(route('two-factor.enable'), false)
            ->assertSee(route('account.export'), false)
            ->assertSee(route('account.destroy'), false)
            ->assertSee('@'.$user->username);
    });

    it('shows profile errors from the Fortify error bag next to the field', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('app.settings'))
            ->put(route('user-profile-information.update'), ['display_name' => '', 'email' => 'nope'])
            ->assertRedirect(route('app.settings'));

        $this->actingAs($user)->get(route('app.settings'))->assertOk()
            ->assertSee('id="f-email-error"', false)
            ->assertSee('aria-invalid="true"', false);
    });

    it('confirms a successful profile update in German', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['status' => 'profile-information-updated'])
            ->get(route('app.settings'))->assertOk()->assertSee('Dein Profil ist gespeichert.');
    });
});
