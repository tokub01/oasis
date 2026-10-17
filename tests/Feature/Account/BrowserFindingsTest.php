<?php

/* Findings of the real-browser click-through on 17 October: German account mails, password confirmation and return. */

use App\Livewire\Account\Settings;
use App\Livewire\Network\Compose;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('sends the account mails with German subjects', function () {
    app()->setLocale('de');
    $user = N::member();

    expect((new VerifyEmail)->toMail($user)->subject)->toBe('Bestätige deine E-Mail-Adresse')
        ->and((new ResetPassword('token'))->toMail($user)->subject)->toBe('Setze dein Passwort zurück');
});

it('says in German that the confirmation password is wrong', function () {
    $this->actingAs(N::member())->post(route('password.confirm'), ['password' => 'falsch'])
        ->assertSessionHasErrors(['password' => 'Das Passwort stimmt nicht.']);
});

it('returns to the part of the settings the member came from after confirming the password', function () {
    $this->actingAs(N::member());

    Livewire::test(Settings::class)->set('confirm', true)->call('deleteAccount')->assertRedirect(route('password.confirm'));
    expect(session('url.intended'))->toBe(route('app.settings').'#loeschen-titel');

    Livewire::test(Settings::class)->call('enableTwoFactor')->assertRedirect(route('password.confirm'));
    expect(session('url.intended'))->toBe(route('app.settings').'#zwei-faktor-titel');
});

it('switches two-factor on by itself after the password confirmation, without a second click', function () {
    $member = N::member();
    $this->actingAs($member);

    Livewire::test(Settings::class)->call('enableTwoFactor')->assertRedirect(route('password.confirm'));
    expect($member->fresh()->two_factor_secret)->toBeNull();

    session()->put('auth.password_confirmed_at', time());
    Livewire::test(Settings::class)->assertSee('Scanne den QR-Code');
    expect($member->fresh()->two_factor_secret)->not->toBeNull()
        ->and(session()->has('settings.resume'))->toBeFalse();
});

it('drops a pending two-factor step when the password was never confirmed', function () {
    $member = N::member();
    $this->actingAs($member);

    Livewire::test(Settings::class)->call('enableTwoFactor');
    Livewire::test(Settings::class)->assertDontSee('Scanne den QR-Code');
    session()->put('auth.password_confirmed_at', time());
    Livewire::test(Settings::class);

    expect($member->fresh()->two_factor_secret)->toBeNull();
});

it('keeps the image panel open once an image is chosen, so a missing description is visible', function () {
    Storage::fake(config('oasis.media_disk'));
    $this->actingAs(N::member());

    $compose = Livewire::test(Compose::class);
    expect($compose->html())->not->toMatch('/<details[^>]*\sopen[\s>]/');

    $compose->set('images', [UploadedFile::fake()->image('foto.jpg')]);
    expect($compose->html())->toMatch('/<details[^>]*\sopen[\s>]/');
});
