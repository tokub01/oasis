<?php

/* Freundschaften (docs/oasis-network.md): request, accept, decline, independent of follows, opt-out, blocks. */

use App\Actions\Network\ManageFriendship;
use App\Actions\Safety\ManageBlock;
use App\Livewire\Network\Friends;
use App\Models\Friendship;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('sends a request with a hint, accepts it on the friends page and keeps follows untouched', function () {
    $me = N::member();
    $mira = N::member();

    $this->actingAs($me)->get(route('oasis.profil', $mira->username))->assertSee('Freundschaft anfragen');
    app(ManageFriendship::class)->request($me, $mira);
    expect($mira->notifications()->count())->toBe(1);

    Livewire::actingAs($mira)->test(Friends::class)->assertSee($me->display_name)->assertSee('Annehmen')
        ->call('accept', $me->id)->assertSet('postStatus', 'Ihr seid jetzt befreundet.');

    expect($me->fresh()->isFriendWith($mira))->toBeTrue()->and($me->follows($mira))->toBeFalse()
        ->and($me->notifications()->count())->toBe(1);
    $this->actingAs($me)->get(route('oasis.profil', $mira->username))->assertSee('Befreundet · beenden');
});

it('turns a request back into a friendship and declines silently', function () {
    $me = N::member();
    $mira = N::member();
    app(ManageFriendship::class)->request($mira, $me);
    app(ManageFriendship::class)->request($me, $mira);
    expect(Friendship::sole()->accepted_at)->not->toBeNull();

    $jonas = N::member();
    app(ManageFriendship::class)->request($jonas, $me);
    Livewire::actingAs($me)->test(Friends::class)->call('remove', $jonas->id);
    expect(Friendship::count())->toBe(1);
});

it('respects the opt-out, members under 18 and blocks', function () {
    $me = N::member();
    $closed = N::member();
    $closed->privacySetting()->create(['friend_requests' => false]);
    expect(fn () => app(ManageFriendship::class)->request($me, $closed))->toThrow(ValidationException::class);
    $this->actingAs($me)->get(route('oasis.profil', $closed->username))->assertDontSee('Freundschaft anfragen');

    $minor = N::member(['birth_date' => now()->subYears(15)->toDateString()]);
    $minor->privacySetting()->create(['profile_audience' => 'members']);
    expect(fn () => app(ManageFriendship::class)->request($me, $minor))->toThrow(ValidationException::class);

    $mira = N::member();
    app(ManageFriendship::class)->request($me, $mira);
    app(ManageBlock::class)->block($mira, $me);
    expect(Friendship::count())->toBe(0);
});
