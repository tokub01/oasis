<?php

use App\Actions\Network\ManageFollows;
use App\Models\Oasis;
use App\Models\Post;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/* Oases: founding, following = membership, posting, house rules, hosts and visibility. */
beforeEach(fn () => $this->withoutVite());

it('founds an oasis with a unique slug and makes the founder its host', function () {
    $founder = N::member();
    $this->actingAs($founder)->get(route('network.oases.create'))->assertOk();

    $input = ['name' => 'Korn & Licht', 'description' => 'Analog.', 'house_rules' => 'Freundlich.', 'visibility' => 'public', 'color' => 'sun'];
    NetworkActions::saveOasis($input, null)->assertRedirect(route('network.oases.show', 'korn-licht'));
    NetworkActions::saveOasis($input, null);

    expect(Oasis::pluck('slug')->all())->toBe(['korn-licht', 'korn-licht-2'])
        ->and(Oasis::first()->isHost($founder))->toBeTrue()
        ->and($founder->follows(Oasis::first()))->toBeTrue();
    NetworkActions::saveOasis(['name' => 'x'], null)->assertHasErrors(['name', 'description', 'house_rules']);
});

it('lets members follow an oasis to post there and shows its Tropfen in the Quelle', function () {
    $oasis = N::oasis(N::member());
    $me = N::member();
    $this->actingAs($me);

    NetworkActions::publish(['body' => 'Darf ich?', 'visibility' => 'public', 'oasis' => $oasis->slug])->assertForbidden();
    NetworkActions::oasis('follow', $oasis)->assertHasNoErrors();
    expect($oasis->isMember($me))->toBeTrue();

    NetworkActions::publish(['body' => 'Jetzt schon', 'visibility' => 'public', 'oasis' => $oasis->slug])->assertHasNoErrors();
    N::post(N::member(), ['body' => 'Aus der Oase', 'oasis_id' => $oasis->id]);

    $this->get(route('app.home'))->assertSee('Aus der Oase')->assertSee('Korn &amp; Licht</a>', false);
    $this->get(route('network.oases.index'))->assertSee('Deine Oasen')->assertSee('Korn &amp; Licht', false);
    NetworkActions::oasis('unfollow', $oasis);
    expect($oasis->isMember($me))->toBeFalse();
});

it('keeps members-only oases and their Tropfen away from guests', function () {
    $host = N::member();
    $oasis = N::oasis($host, ['visibility' => 'members', 'name' => 'Lesekreis']);
    NetworkActions::publish(['body' => 'Im Kreis', 'visibility' => 'public', 'oasis' => $oasis->slug], $host);
    expect(Post::sole()->visibility->value)->toBe('members');

    auth()->logout();
    $this->get(route('network.oases.show', $oasis))->assertNotFound();
    $this->get(route('network.posts.show', Post::sole()))->assertNotFound();
    $public = N::oasis($host, ['name' => 'Offen']);
    $this->get(route('network.oases.show', $public))->assertOk()->assertSee('Hausregeln')->assertSee('Du liest als Gast.');
});

it('lets hosts remove Tropfen from their oasis: hidden for others, visible to the author', function () {
    $host = N::member();
    $oasis = N::oasis($host);
    $author = N::member();
    $post = N::post($author, ['oasis_id' => $oasis->id, 'body' => 'Regelbruch']);

    NetworkActions::post('removePostFromOasis', $post->id, N::member())->assertForbidden();
    $this->actingAs($host)->get(route('network.oases.show', $oasis))->assertSee('Aus Oase entfernen');
    NetworkActions::post('removePostFromOasis', $post->id)->assertHasNoErrors();
    $this->get(route('network.oases.show', $oasis))->assertDontSee('Regelbruch');
    NetworkActions::post('removePostFromOasis', $post->id)->assertForbidden();

    $this->actingAs($author)->get(route('network.posts.show', $post))->assertOk()
        ->assertSee('Regelbruch')->assertSee('Nur du siehst ihn noch.');
    $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertNotFound();
});

it('edits an oasis and manages hosts; the last host can neither step down nor leave', function () {
    $host = N::member();
    $oasis = N::oasis($host);
    $member = N::member();
    $outsider = N::member();
    app(ManageFollows::class)->follow($member, $oasis);

    $this->actingAs($member)->get(route('network.oases.edit', $oasis))->assertForbidden();
    $this->actingAs($host)->get(route('network.oases.edit', $oasis))->assertOk()->assertSee('Zu Gastgeber:in machen');
    NetworkActions::saveOasis(['name' => 'Neues Licht', 'description' => 'Neu.', 'house_rules' => 'Neu.', 'visibility' => 'members', 'color' => 'night'], $oasis)->assertRedirect(route('network.oases.show', $oasis));
    expect($oasis->fresh()->name)->toBe('Neues Licht');

    NetworkActions::role($oasis, $host, 'member')->assertHasErrors('role');
    NetworkActions::oasis('unfollow', $oasis)->assertHasErrors('follow');
    NetworkActions::role($oasis, $outsider, 'host')->assertHasErrors('role');

    NetworkActions::role($oasis, $member, 'host')->assertHasNoErrors();
    expect($oasis->isHost($member))->toBeTrue();
    NetworkActions::role($oasis, $host, 'member');
    expect($oasis->isHost($host))->toBeFalse();
});

it('gives read-only accounts no way to found oases or follow them', function () {
    $oasis = N::oasis(N::member());
    $this->actingAs(N::readOnlyMember());
    $this->get(route('network.oases.create'))->assertForbidden();
    NetworkActions::oasis('follow', $oasis)->assertForbidden();
    $this->get(route('network.oases.index'))->assertOk()->assertDontSee('Oase eröffnen');
});
