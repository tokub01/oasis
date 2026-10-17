<?php

use App\Actions\Network\ManageFollows;
use App\Actions\Safety\ManageMute;
use App\Models\Mute;
use App\Queries\Network\PostFeed;
use Illuminate\Validation\ValidationException;
use Tests\Support\NetworkFixtures as Fixtures;

it('mutes an account unilaterally and keeps profile accessible', function (): void {
    $alice = Fixtures::member(['username' => 'alice', 'display_name' => 'Alice']);
    $bob = Fixtures::member(['username' => 'bob', 'display_name' => 'Bob']);

    $manage = app(ManageMute::class);
    $mute = $manage->mute($alice, $bob);

    expect($mute)->toBeInstanceOf(Mute::class)
        ->and($alice->fresh()->hasMuted($bob))->toBeTrue()
        ->and($bob->fresh()->hasMuted($alice))->toBeFalse()
        ->and($alice->fresh()->mutedUserIds())->toBe([$bob->id])
        ->and($bob->fresh()->mutedUserIds())->toBe([]);

    // Profile of Bob remains accessible to Alice
    $this->actingAs($alice)
        ->get(route('oasis.profil', 'bob'))
        ->assertOk();
});

it('prevents muting yourself', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $manage = app(ManageMute::class);

    $manage->mute($alice, $alice);
})->throws(ValidationException::class);

it('hides Tropfen of muted account from Quelle, Entdecken, Oasen and replies for the muter only', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $oasis = Fixtures::oasis($alice);
    $bobPost = Fixtures::post($bob, ['body' => 'Bobs Tropfen in Oase', 'oasis_id' => $oasis->id]);
    $replyByBob = Fixtures::post($bob, ['body' => 'Bobs Antwort', 'reply_to_id' => $bobPost->id]);

    $feed = app(PostFeed::class);

    // Before muting: visible in oasis
    expect($feed->inOasis($oasis, $alice)->pluck('id'))->toContain($bobPost->id);

    app(ManageMute::class)->mute($alice, $bob);

    // After muting: hidden from Alice's oasis feed, discover, and replies
    expect($feed->inOasis($oasis, $alice)->pluck('id'))->not->toContain($bobPost->id)
        ->and($feed->discover($alice)->pluck('id'))->not->toContain($bobPost->id)
        ->and($feed->replies($bobPost, $alice)->pluck('id'))->not->toContain($replyByBob->id);

    // But visible to a third party (or Bob himself)
    $charlie = Fixtures::member(['username' => 'charlie']);
    expect($feed->inOasis($oasis, $charlie)->pluck('id'))->toContain($bobPost->id);
});

it('unmutes an account and restores posts in feeds', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $bob->profile()->create(['visibility' => 'public', 'avatar_color' => 'sun']);
    $bobPost = Fixtures::post($bob, ['body' => 'Bobs Tropfen']);
    app(ManageFollows::class)->follow($alice, $bob);

    $manage = app(ManageMute::class);
    $manage->mute($alice, $bob);

    $feed = app(PostFeed::class);
    expect($alice->fresh()->hasMuted($bob))->toBeTrue()
        ->and($feed->source($alice)->pluck('id'))->not->toContain($bobPost->id)
        ->and($feed->discover($alice)->pluck('id'))->not->toContain($bobPost->id);

    $manage->unmute($alice, $bob);

    expect($alice->fresh()->hasMuted($bob))->toBeFalse()
        ->and($feed->source($alice)->pluck('id'))->toContain($bobPost->id)
        ->and($feed->discover($alice)->pluck('id'))->toContain($bobPost->id);
});

it('replaces the quote of a muted person in a carried Tropfen, only for the muter', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $carol = Fixtures::member(['username' => 'carol']);
    $original = Fixtures::post($bob, ['body' => 'Bobs Originaltext']);
    $carry = Fixtures::post($carol, ['body' => 'Carol trägt weiter', 'carried_post_id' => $original->id]);

    app(ManageMute::class)->mute($alice, $bob);

    $this->actingAs($alice)->get(route('network.posts.show', $carry))->assertOk()
        ->assertSee('Carol trägt weiter')->assertSee('Tropfen einer stummgeschalteten Person.')
        ->assertDontSee('Bobs Originaltext');
    $this->actingAs($carol)->get(route('network.posts.show', $carry))->assertOk()
        ->assertSee('Bobs Originaltext')->assertDontSee('Tropfen einer stummgeschalteten Person.');
});
