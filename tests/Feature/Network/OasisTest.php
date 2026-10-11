<?php

use App\Actions\Network\ManageFollows;
use App\Models\Oasis;
use App\Models\Post;
use Tests\Support\NetworkFixtures as N;

/* Oases: founding, following = membership, posting, house rules, hosts and visibility. */
beforeEach(fn () => $this->withoutVite());

it('founds an oasis with a unique slug and makes the founder its host', function () {
    $founder = N::member();
    $this->actingAs($founder)->get(route('network.oases.create'))->assertOk();

    $input = ['name' => 'Korn & Licht', 'description' => 'Analog.', 'house_rules' => 'Freundlich.', 'visibility' => 'public', 'color' => 'sun'];
    $this->post(route('network.oases.store'), $input)->assertRedirect(route('network.oases.show', 'korn-licht'));
    $this->post(route('network.oases.store'), $input);

    expect(Oasis::pluck('slug')->all())->toBe(['korn-licht', 'korn-licht-2'])
        ->and(Oasis::first()->isHost($founder))->toBeTrue()
        ->and($founder->follows(Oasis::first()))->toBeTrue();
    $this->post(route('network.oases.store'), ['name' => 'x'])->assertSessionHasErrors(['name', 'description', 'house_rules']);
});

it('lets members follow an oasis to post there and shows its Tropfen in the Quelle', function () {
    $oasis = N::oasis(N::member());
    $me = N::member();
    $this->actingAs($me);

    $this->post(route('network.posts.store'), ['body' => 'Darf ich?', 'visibility' => 'public', 'oasis' => $oasis->slug])->assertForbidden();
    $this->post(route('network.follow.oasis', $oasis))->assertRedirect();
    expect($oasis->isMember($me))->toBeTrue();

    $this->post(route('network.posts.store'), ['body' => 'Jetzt schon', 'visibility' => 'public', 'oasis' => $oasis->slug])
        ->assertRedirect(route('network.oases.show', $oasis));
    N::post(N::member(), ['body' => 'Aus der Oase', 'oasis_id' => $oasis->id]);

    $this->get(route('app.home'))->assertSee('Aus der Oase')->assertSee('Korn &amp; Licht</a>', false);
    $this->get(route('network.oases.index'))->assertSee('Deine Oasen')->assertSee('Korn &amp; Licht', false);
    $this->delete(route('network.unfollow.oasis', $oasis));
    expect($oasis->isMember($me))->toBeFalse();
});

it('keeps members-only oases and their Tropfen away from guests', function () {
    $host = N::member();
    $oasis = N::oasis($host, ['visibility' => 'members', 'name' => 'Lesekreis']);
    $this->actingAs($host)->post(route('network.posts.store'), ['body' => 'Im Kreis', 'visibility' => 'public', 'oasis' => $oasis->slug]);
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

    $this->actingAs(N::member())->post(route('network.posts.remove', $post))->assertForbidden();
    $this->actingAs($host)->get(route('network.oases.show', $oasis))->assertSee('Aus Oase entfernen');
    $this->post(route('network.posts.remove', $post))->assertRedirect(route('network.oases.show', $oasis));
    $this->get(route('network.oases.show', $oasis))->assertDontSee('Regelbruch');
    $this->post(route('network.posts.remove', $post))->assertForbidden();

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
    $this->put(route('network.oases.update', $oasis), ['name' => 'Neues Licht', 'description' => 'Neu.', 'house_rules' => 'Neu.', 'visibility' => 'members', 'color' => 'night'])
        ->assertRedirect(route('network.oases.show', $oasis));
    expect($oasis->fresh()->name)->toBe('Neues Licht');

    $this->put(route('network.oases.role', [$oasis, $host]), ['role' => 'member'])->assertSessionHasErrors('role');
    $this->delete(route('network.unfollow.oasis', $oasis))->assertSessionHasErrors('follow');
    $this->put(route('network.oases.role', [$oasis, $outsider]), ['role' => 'host'])->assertSessionHasErrors('role');

    $this->put(route('network.oases.role', [$oasis, $member]), ['role' => 'host'])->assertRedirect();
    expect($oasis->isHost($member))->toBeTrue();
    $this->put(route('network.oases.role', [$oasis, $host]), ['role' => 'member']);
    expect($oasis->isHost($host))->toBeFalse();
});

it('gives read-only accounts no way to found oases or follow them', function () {
    $oasis = N::oasis(N::member());
    $this->actingAs(N::readOnlyMember());
    $this->get(route('network.oases.create'))->assertForbidden();
    $this->post(route('network.follow.oasis', $oasis))->assertForbidden();
    $this->get(route('network.oases.index'))->assertOk()->assertDontSee('Oase eröffnen');
});
