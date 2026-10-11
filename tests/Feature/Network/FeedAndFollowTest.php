<?php

use App\Models\Follow;
use App\Models\User;
use App\Queries\Network\PostFeed;
use Tests\Support\NetworkFixtures as N;

/* Quelle, Entdecken, following people and profiles. */
beforeEach(fn () => $this->withoutVite());

it('shows own Tropfen and those of followed people in order, without replies or strangers', function () {
    $me = N::member();
    $friend = N::member();
    $stranger = N::member();
    $old = N::post($friend, ['body' => 'Alt von Freundin']);
    N::post($stranger, ['body' => 'Fremder Tropfen']);
    N::post($friend, ['body' => 'Antwort von Freundin', 'reply_to_id' => $old->id]);
    N::post($me, ['body' => 'Mein Tropfen']);

    $this->actingAs($me)->post(route('network.follow.user', $friend))->assertRedirect();
    $this->get(route('app.home'))->assertOk()
        ->assertSeeInOrder(['Mein Tropfen', 'Alt von Freundin'])
        ->assertDontSee('Fremder Tropfen')->assertDontSee('Antwort von Freundin')->assertDontSee('Nächste Schritte');
});

it('pages with a cursor and ends honestly', function () {
    $me = N::member();
    foreach (range(1, PostFeed::PER_PAGE + 1) as $number) {
        N::post($me, ['body' => 'Tropfen Nummer '.$number]);
    }

    $first = $this->actingAs($me)->get(route('app.home'))->assertSee('Ältere Tropfen')->assertDontSee('Du bist auf dem neuesten Stand.');
    $next = $first->viewData('posts')->nextPageUrl();
    $this->get($next)->assertSee('Tropfen Nummer 1<', false)->assertSee('Du bist auf dem neuesten Stand.');
});

it('shows first steps to members who follow nobody', function () {
    $this->actingAs(N::member())->get(route('app.home'))->assertSee('Nächste Schritte')->assertSee('Noch keine Tropfen.');
});

it('follows and unfollows idempotently, never oneself, never hidden profiles', function () {
    $me = N::member();
    $other = N::member();
    $this->actingAs($me);

    $this->post(route('network.follow.user', $other));
    $this->post(route('network.follow.user', $other));
    expect(Follow::count())->toBe(1);
    $this->get(route('oasis.profil', $other->username))->assertSee('Karawane: 1')->assertSee('Gefolgt');

    $this->post(route('network.follow.user', $me))->assertSessionHasErrors('follow');
    $this->delete(route('network.unfollow.user', $other));
    expect(Follow::count())->toBe(0);

    $unverified = User::factory()->unverified()->create();
    $this->post(route('network.follow.user', $unverified))->assertNotFound();
});

it('lets read-only accounts unfollow but not follow', function () {
    $user = N::readOnlyMember();
    $other = N::member();
    Follow::create(['follower_id' => $user->id, 'followable_type' => $other->getMorphClass(), 'followable_id' => $other->id]);

    $this->actingAs($user)->post(route('network.follow.user', N::member()))->assertForbidden();
    $this->delete(route('network.unfollow.user', $other))->assertRedirect();
    expect(Follow::count())->toBe(0);
});

it('shows profiles to guests only when public and falls back to sample profiles', function () {
    $hidden = N::member();
    $public = N::member();
    $public->profile()->create(['visibility' => 'public', 'bio' => 'Ich fotografiere.', 'location' => 'Leipzig']);
    N::post($public, ['body' => 'Öffentlicher Tropfen']);
    N::post($public, ['body' => 'Geheimer Tropfen', 'visibility' => 'members']);

    $this->get(route('oasis.profil', $hidden->username))->assertNotFound();
    $this->get(route('oasis.profil', strtoupper($public->username)))->assertOk()
        ->assertSee('Ich fotografiere.')->assertSee('Leipzig')->assertSee('Öffentlicher Tropfen')->assertDontSee('Geheimer Tropfen');
    $this->get(route('oasis.profil', 'mira'))->assertOk()->assertSee('Beispiel');
    config(['oasis.demo_pages' => false]);
    $this->get(route('oasis.profil', 'mira'))->assertNotFound();
    $this->get(route('oasis.profil', 'niemand'))->assertNotFound();
});

it('edits the own profile', function () {
    $me = N::member();
    $this->actingAs($me)->get(route('network.profile.edit'))->assertOk()->assertSee('Dein Profil');

    $this->put(route('network.profile.update'), ['bio' => 'Hallo', 'location' => 'Krefeld', 'visibility' => 'public', 'avatar_color' => 'night'])
        ->assertRedirect(route('network.profile.edit'));
    expect($me->fresh()->profile->only(['bio', 'location']))->toBe(['bio' => 'Hallo', 'location' => 'Krefeld'])
        ->and($me->fresh()->profile->avatar_color->value)->toBe('night');
    $this->put(route('network.profile.update'), ['visibility' => 'everyone', 'avatar_color' => 'pink'])
        ->assertSessionHasErrors(['visibility', 'avatar_color']);
});

it('lists public Tropfen of public profiles in Entdecken', function () {
    $public = N::member();
    $public->profile()->create(['visibility' => 'public']);
    N::post($public, ['body' => 'Sichtbar beim Entdecken']);
    N::post(N::member(), ['body' => 'Profil nur für Mitglieder']);

    $this->actingAs(N::member())->get(route('network.discover'))->assertOk()
        ->assertSee('Sichtbar beim Entdecken')->assertDontSee('Profil nur für Mitglieder');
});

it('links the app navigation to the network pages', function () {
    $me = N::member();
    $this->actingAs($me)->get(route('app.home'))
        ->assertSee(route('network.discover'))->assertSee(route('network.oases.index'))
        ->assertSee(route('oasis.profil', $me->username))->assertSee(route('network.stats'))
        ->assertSee(route('network.invitations.index'));
});
