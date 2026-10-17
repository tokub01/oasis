<?php

/* Stage 13: Privatsphäre-Center, guided check and the central audience rule PrivacySetting::allows(). */

use App\Actions\Network\ManageFollows;
use App\Enums\Privacy\Audience;
use App\Livewire\Privacy\Center;
use App\Livewire\Privacy\Checkup;
use App\Models\PrivacySetting;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('shows every setting with privacy-by-default values and writes nothing when opened', function () {
    $this->actingAs(N::member())->get(route('app.privacy'))->assertOk()->assertSeeLivewire(Center::class)
        ->assertSee('Wer darf dein Profil sehen?')->assertSee('Wer darf dich erwähnen?')->assertSee('In der Oasis-Suche auffindbar')
        ->assertSee(route('app.privacy.check'), false);

    Livewire::test(Center::class)
        ->assertSet('settings.profile_audience', 'members')->assertSet('settings.mentions_audience', 'members')
        ->assertSet('settings.searchable', true)->assertSet('settings.search_engines', false);
    expect(PrivacySetting::count())->toBe(0);
});

it('saves a changed setting at once and confirms it', function () {
    $me = N::member();
    $this->actingAs($me);

    Livewire::test(Center::class)->set('settings.profile_audience', 'following')
        ->assertHasNoErrors()->assertSee('Gespeichert: Wer darf dein Profil sehen?')
        ->set('settings.searchable', false)->assertSee('Gespeichert: In der Oasis-Suche auffindbar');

    $stored = $me->fresh()->privacy();
    expect($stored->profile_audience)->toBe(Audience::Following)->and($stored->searchable)->toBeFalse()
        ->and($stored->mentions_audience)->toBe(Audience::Members);
});

it('refuses choices a setting does not offer and keeps the stored value', function () {
    $me = N::member();
    $this->actingAs($me);

    // "Alle – auch ohne Konto" is offered for the profile, never for mentions.
    Livewire::test(Center::class)->set('settings.mentions_audience', 'everyone')
        ->assertHasErrors('settings.mentions_audience')->assertSet('settings.mentions_audience', 'members');
    Livewire::test(Center::class)->set('settings.default_post_visibility', 'following')
        ->assertHasErrors('settings.default_post_visibility');
    expect(PrivacySetting::count())->toBe(0);
});

it('rejects settings that do not exist', function () {
    $this->actingAs(N::member());

    Livewire::test(Center::class)->set('settings.role', 'admin')->assertStatus(422);
    expect(PrivacySetting::count())->toBe(0);
});

it('keeps the privacy pages for signed-in, verified members', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
    $this->actingAs(N::member(['email_verified_at' => null]))->get(route($route))->assertRedirect(route('verification.notice'));
})->with(['app.privacy', 'app.privacy.check']);

it('walks through the check step by step, saving on the way, and ends with a summary', function () {
    $me = N::member();
    $this->actingAs($me)->get(route('app.privacy.check'))->assertOk()->assertSeeLivewire(Checkup::class)
        ->assertSee('Schritt 1 von 3');

    Livewire::test(Checkup::class)
        ->set('settings.profile_audience', 'everyone')->assertSee('Gespeichert')
        ->call('next')->assertSee('Wer darf dich erwähnen?')->assertSee('Schritt 2 von 3')
        ->call('next')->call('next')->assertSee('Alles im Blick')->assertSee('Alle – auch ohne Konto')
        ->call('next')->assertSet('step', 4)
        ->call('back')->call('back')->call('back')->call('back')->assertSet('step', 1);

    expect($me->fresh()->privacy()->profile_audience)->toBe(Audience::Everyone);
});

it('moves between steps only through Weiter and Zurück', function () {
    $this->actingAs(N::member());

    expect(fn () => Livewire::test(Checkup::class)->set('step', 99))->toThrow(CannotUpdateLockedPropertyException::class);
    Livewire::test(Checkup::class)->call('next')->assertDispatched('oasis:focus', id: 'check-titel');
});

describe('PrivacySetting::allows', function () {
    function privacyOf(User $owner, array $values): PrivacySetting
    {
        return $owner->privacySetting()->updateOrCreate([], $values);
    }

    it('lets everyone, including guests, in for "Alle"', function () {
        $settings = privacyOf(N::member(), ['profile_audience' => 'everyone']);

        expect($settings->allows('profile_audience', null))->toBeTrue()
            ->and($settings->allows('profile_audience', N::member(['email_verified_at' => null])))->toBeTrue();
    });

    it('requires a verified member for "Alle Mitglieder"', function () {
        $settings = privacyOf(N::member(), ['mentions_audience' => 'members']);

        expect($settings->allows('mentions_audience', null))->toBeFalse()
            ->and($settings->allows('mentions_audience', N::member(['email_verified_at' => null])))->toBeFalse()
            ->and($settings->allows('mentions_audience', N::member()))->toBeTrue();
    });

    it('only lets in people the member follows for "Menschen, denen du folgst" – followers are not enough', function () {
        $owner = N::member();
        $followed = N::member();
        $follower = N::member();
        app(ManageFollows::class)->follow($owner, $followed);
        app(ManageFollows::class)->follow($follower, $owner);
        $settings = privacyOf($owner, ['replies_audience' => 'following']);

        expect($settings->allows('replies_audience', $followed))->toBeTrue()
            ->and($settings->allows('replies_audience', $follower))->toBeFalse()
            ->and($settings->allows('replies_audience', null))->toBeFalse();
    });

    it('lets nobody in for "Niemand", except the member themselves', function () {
        $owner = N::member();
        $settings = privacyOf($owner, ['chat_invites_audience' => 'nobody']);

        expect($settings->allows('chat_invites_audience', N::member()))->toBeFalse()
            ->and($settings->allows('chat_invites_audience', $owner))->toBeTrue();
    });

    it('uses privacy-by-default values for accounts without a stored row', function () {
        $owner = N::member();

        expect($owner->privacy()->exists)->toBeFalse()
            ->and($owner->privacy()->allows('profile_audience', null))->toBeFalse()
            ->and($owner->privacy()->allows('profile_audience', N::member()))->toBeTrue()
            ->and($owner->privacy()->search_engines)->toBeFalse();
    });
});
