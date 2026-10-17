<?php

/*
 * Bot protection without captcha or tracking (docs/oasis-bot-protection.md): honeypot and time trap, throwaway
 * addresses, registrations per IP, limits for new accounts, duplicates. phpunit.xml switches the time trap and the
 * new-account hours off for the other tests; these tests switch them on.
 */

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Network\ManageFollows;
use App\Actions\Network\PublishPost;
use App\Enums\Role;
use App\Livewire\Admin\System;
use App\Livewire\Oasis\ContentNotice;
use App\Livewire\Oasis\WaitlistSignup;
use App\Models\ContentReport;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Security\AccountLimits;
use App\Support\Security\BotProtection;
use App\Support\Security\DisposableEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Mail::fake();
    RateLimiter::clear('registration|127.0.0.1');
    RateLimiter::clear('registration-day:127.0.0.1');
    config(['oasis.bot_protection.time_trap' => true, 'oasis.bot_protection.new_account_hours' => 24]);
    DisposableEmail::flush();
});

afterEach(function () {
    @unlink(storage_path(DisposableEmail::UPDATED));
    DisposableEmail::flush();
});

/** Registration input with a time stamp issued $secondsAgo. */
function signup(array $overrides = [], int $secondsAgo = 10): array
{
    test()->travel(-$secondsAgo)->seconds();
    $token = BotProtection::token();
    test()->travelBack();

    return array_replace(['display_name' => 'Echter Mensch', 'username' => 'echter_mensch', 'email' => 'mensch@example.tld',
        'birth_date' => '1990-06-15', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        'terms' => '1', BotProtection::FIELD => '', BotProtection::TOKEN => $token], $overrides);
}

describe('registration', function () {
    it('accepts a person who took a few seconds and shows the traps in the form', function () {
        $this->get(route('register'))->assertOk()->assertSee('name="'.BotProtection::TOKEN.'"', false)
            ->assertSee('name="'.BotProtection::FIELD.'"', false);
        $this->post(route('register'), signup())->assertRedirect('/app');
        expect(User::count())->toBe(1);
    });

    it('refuses bots: filled honeypot, no or forged time stamp, too fast, a day-old form', function (array $input, int $secondsAgo) {
        $this->post(route('register'), signup($input, $secondsAgo))->assertSessionHasErrors('form');
        expect(User::count())->toBe(0)->and(BotProtection::today())->not->toBe([]);
    })->with([
        'honeypot' => [[BotProtection::FIELD => 'https://spam.tld'], 10],
        'no stamp' => [[BotProtection::TOKEN => null], 10],
        'forged stamp' => [[BotProtection::TOKEN => 'abc'], 10],
        'too fast' => [[], 1],
        'day old' => [[], 25 * 3600],
    ]);

    it('refuses throwaway addresses, also their subdomains, unless allowed', function () {
        $this->post(route('register'), signup(['email' => 'wegwerf@mailinator.com']))->assertSessionHasErrors('email');
        $this->post(route('register'), signup(['email' => 'x@inbox.mailinator.com']))->assertSessionHasErrors('email');
        expect(User::count())->toBe(0);

        config(['oasis.bot_protection.allowed_email_domains' => ['mailinator.com']]);
        $this->post(route('register'), signup(['email' => 'erlaubt@mailinator.com']))->assertRedirect('/app');
    });

    it('limits new accounts per IP address and day', function () {
        config(['oasis.bot_protection.registrations_per_ip_per_day' => 2]);
        foreach ([1, 2] as $i) {
            $this->post(route('register'), signup(['username' => 'mensch_'.$i, 'email' => 'mensch'.$i.'@example.tld']))->assertRedirect('/app');
            auth()->logout();
        }
        $this->travel(2)->minutes();

        $this->post(route('register'), signup(['username' => 'mensch_3', 'email' => 'mensch3@example.tld']))->assertSessionHasErrors('form');
        expect(User::count())->toBe(2)->and(BotProtection::today())->toHaveKey('registration_ip');
    });

    it('refuses a throwaway address when the e-mail is changed, but keeps an old one when only the name changes', function () {
        $user = N::member(['email' => 'alt@mailinator.com']);
        $update = app(UpdateUserProfileInformation::class);

        $update->update($user, ['display_name' => 'Neuer Name', 'email' => 'alt@mailinator.com']);
        expect($user->fresh()->display_name)->toBe('Neuer Name');
        expect(fn () => $update->update($user->fresh(), ['display_name' => 'Neuer Name', 'email' => 'neu@yopmail.com']))
            ->toThrow(ValidationException::class);
    });
});

describe('public forms', function () {
    it('refuses a form sent faster than three seconds and accepts it afterwards', function () {
        $form = Livewire::test(WaitlistSignup::class)->set('form.email', 'start@example.tld')->set('form.consent', true);

        $form->call('submit')->assertHasErrors('throttle');
        expect(WaitlistEntry::count())->toBe(0);

        // A refusal issues a fresh stamp: the three seconds start again.
        $this->travel(1)->seconds();
        $form->call('submit')->assertHasErrors('throttle');
        $this->travel(5)->seconds();
        $form->call('submit')->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.gruendung'));
        expect(WaitlistEntry::count())->toBe(1);
    });

    it('refuses throwaway addresses on the waitlist but never blocks a report under Art. 16 DSA', function () {
        $waitlist = Livewire::test(WaitlistSignup::class)->set('form.email', 'start@yopmail.com')->set('form.consent', true);
        $report = Livewire::test(ContentNotice::class)->set('form.url', 'https://example.tld/tropfen/1')
            ->set('form.reason', 'Eine Beleidigung.')->set('form.name', 'Meldende Person')
            ->set('form.email', 'melde@yopmail.com')->set('form.truthful', true);
        $this->travel(5)->seconds();

        $waitlist->call('submit')->assertHasErrors('form.email');
        $report->call('submit')->assertHasNoErrors();
        expect(WaitlistEntry::count())->toBe(0)->and(ContentReport::where('email', 'melde@yopmail.com')->exists())->toBeTrue();
    });
});

describe('new accounts', function () {
    it('limits links and mentions of new accounts, not of established ones', function () {
        $new = N::member();
        $old = N::member();
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        foreach (['anna', 'ben', 'cem', 'dora'] as $name) {
            N::member(['username' => $name]);
        }
        $publish = app(PublishPost::class);

        expect(fn () => $publish->handle($new, ['body' => 'https://a.tld https://b.tld www.c.tld', 'visibility' => 'members']))
            ->toThrow(ValidationException::class);
        expect(fn () => $publish->handle($new, ['body' => '@anna @ben @cem @dora', 'visibility' => 'members']))
            ->toThrow(ValidationException::class);
        $publish->handle($new, ['body' => 'Zwei Links reichen: https://a.tld https://b.tld', 'visibility' => 'members']);
        $publish->handle($old, ['body' => 'https://a.tld https://b.tld www.c.tld @anna @ben @cem @dora', 'visibility' => 'members']);
        expect(BotProtection::today())->toHaveKeys(['content_links', 'content_mentions']);
    });

    it('limits Tropfen per hour for new accounts', function () {
        $new = N::member();
        $publish = app(PublishPost::class);
        foreach (range(1, 10) as $i) {
            $publish->handle($new, ['body' => 'Tropfen Nummer '.$i, 'visibility' => 'members']);
        }

        expect(fn () => $publish->handle($new, ['body' => 'Tropfen Nummer 11', 'visibility' => 'members']))->toThrow(ValidationException::class);
        $this->travel(61)->minutes();
        $publish->handle($new, ['body' => 'Tropfen Nummer 11', 'visibility' => 'members']);
    });

    it('limits follows per hour, tighter for new accounts', function () {
        $new = N::member();
        $follows = app(ManageFollows::class);
        foreach (range(1, AccountLimits::HOURLY['follows'][0]) as $i) {
            $follows->follow($new, N::member());
        }

        expect(fn () => $follows->follow($new, N::member()))->toThrow(ValidationException::class);
        expect(BotProtection::today())->toHaveKey('limit_follows');
    });

    it('refuses the same text twice within ten minutes, for everyone', function () {
        $user = N::member();
        $user->forceFill(['created_at' => now()->subYear()])->save();
        $publish = app(PublishPost::class);
        $publish->handle($user, ['body' => 'Kauft jetzt!', 'visibility' => 'members']);

        expect(fn () => $publish->handle($user, ['body' => '  kauft   JETZT! ', 'visibility' => 'members']))->toThrow(ValidationException::class);
        $this->travel(11)->minutes();
        $publish->handle($user, ['body' => 'Kauft jetzt!', 'visibility' => 'members']);
    });
});

describe('throwaway list and overview', function () {
    it('refreshes the list from the open project and ignores a broken download', function () {
        Http::fake(['raw.githubusercontent.com/*' => Http::sequence()
            ->push(implode("\n", array_map(fn ($i) => 'wegwerf'.$i.'.tld', range(1, 1200))))
            ->push("kein-domain\n<html>")]);

        $this->artisan('oasis:disposable-domains-update')->expectsOutputToContain('1200 Wegwerf-Domains')->assertSuccessful();
        expect(DisposableEmail::isDisposable('a@wegwerf7.tld'))->toBeTrue()->and(DisposableEmail::isDisposable('a@mailinator.com'))->toBeFalse();

        $this->artisan('oasis:disposable-domains-update')->expectsOutputToContain('bisherige Liste bleibt')->assertFailed();
        expect(DisposableEmail::isDisposable('a@wegwerf7.tld'))->toBeTrue();
    });

    it('keeps the big mail providers off the bundled list', function (string $domain) {
        expect(DisposableEmail::isDisposable('person@'.$domain))->toBeFalse();
    })->with(['gmail.com', 'web.de', 'gmx.de', 'gmx.net', 'outlook.com', 'hotmail.com', 't-online.de', 'icloud.com', 'yahoo.com', 'posteo.de', 'mailbox.org', 'proton.me']);

    it('shows today’s refusals to admins on the system page', function () {
        BotProtection::count('honeypot');
        BotProtection::count('honeypot');
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);

        Livewire::actingAs($admin)->test(System::class)->assertSee('Abgewehrte Bot-Versuche heute')->assertSee('Unsichtbares Feld ausgefüllt')->assertSee('2');
    });
});
