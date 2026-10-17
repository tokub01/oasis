<?php

/* Einstieg für neue Mitglieder: led there once, every step and the whole tour skippable, choices saved on the way. */

use App\Enums\Privacy\Audience;
use App\Livewire\Network\Source;
use App\Livewire\Onboarding\Welcome;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

function newcomer(array $attributes = [])
{
    return N::member($attributes + ['onboarded_at' => null]);
}

it('leads a new member to the introduction once and never again after finishing', function () {
    $member = newcomer();

    Livewire::actingAs($member)->test(Source::class)->assertRedirect(route('app.welcome'));
    Livewire::actingAs($member)->test(Welcome::class)->assertSee('Über dich')->assertSee('Schritt 1 von 4')
        ->call('finish')->assertRedirect(route('app.home'));

    expect($member->fresh()->onboarded_at)->not->toBeNull();
    Livewire::actingAs($member->fresh())->test(Source::class)->assertNoRedirect();
    $this->actingAs(N::member())->get(route('app.home'))->assertOk();
});

it('walks through all steps, saving profile and privacy, and lets every step be skipped', function () {
    $member = newcomer();
    $oasis = N::oasis(N::member(), ['name' => 'Analoges Licht']);

    $tour = Livewire::actingAs($member)->test(Welcome::class)
        ->set('bio', 'Ich gärtnere auf dem Balkon.')->set('location', 'Leipzig')->set('avatar_color', 'spring')->call('next')
        ->assertSet('step', 2)->assertSee('Wer dich sieht')
        ->set('profile_audience', 'everyone')->call('next')
        ->assertSet('step', 3)->assertSee('Analoges Licht')
        ->call('toggleOasis', $oasis->id)->assertSee('Beigetreten')
        ->call('skipStep')->assertSet('step', 4)->assertSee('Menschen finden')
        ->call('back')->assertSet('step', 3);

    expect($member->fresh()->profile->bio)->toBe('Ich gärtnere auf dem Balkon.')
        ->and($member->fresh()->privacy()->profile_audience)->toBe(Audience::Everyone)
        ->and($member->fresh()->follows($oasis))->toBeTrue();

    $tour->call('skipStep')->call('finish')->assertRedirect(route('app.home'));
});

it('skips a step without saving it', function () {
    $member = newcomer();

    Livewire::actingAs($member)->test(Welcome::class)->set('bio', 'Nicht speichern')->call('skipStep')->assertSet('step', 2);

    expect($member->fresh()->profile)->toBeNull();
});

it('offers members under 18 only the choices they may make and refuses others', function () {
    $minor = newcomer(['birth_date' => now()->subYears(16)->toDateString()]);

    Livewire::actingAs($minor)->test(Welcome::class)->call('skipStep')
        ->assertDontSee('Alle – auch ohne Konto')->set('profile_audience', 'everyone')->call('next')
        ->assertHasErrors('profile_audience')->assertSet('step', 2);
});

it('suggests hand-picked oases when configured, otherwise the largest public ones, never members-only ones', function () {
    $host = N::member();
    $hidden = N::oasis($host, ['name' => 'Geschlossene Runde', 'visibility' => 'members']);
    $picked = N::oasis($host, ['name' => 'Ausgewählt']);
    config(['oasis.onboarding.featured_oases' => [$picked->slug]]);

    Livewire::actingAs(newcomer())->test(Welcome::class)->call('skipStep')->call('skipStep')
        ->assertSee('Ausgewählt')->assertDontSee('Geschlossene Runde')
        ->call('toggleOasis', $hidden->id)->assertNotFound();
});

it('can be opened again from the settings and keeps the step locked for the client', function () {
    $member = N::member();
    $this->actingAs($member)->get(route('app.settings'))->assertSee(route('app.welcome'), false);
    $this->actingAs($member)->get(route('app.welcome'))->assertOk()->assertSee('Willkommen');

    expect(fn () => Livewire::actingAs($member)->test(Welcome::class)->set('step', 4))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
