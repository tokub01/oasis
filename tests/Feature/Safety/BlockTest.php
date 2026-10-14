<?php

use App\Actions\Network\ManageFollows;
use App\Actions\Network\ManageOasis;
use App\Actions\Safety\ManageBlock;
use App\Enums\Network\OasisRole;
use App\Models\Block;
use App\Queries\Network\PostFeed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Support\ChatFixtures;
use Tests\Support\NetworkFixtures as Fixtures;

it('blocks an account and removes existing follows in both directions', function (): void {
    $alice = Fixtures::member(['username' => 'alice', 'display_name' => 'Alice']);
    $bob = Fixtures::member(['username' => 'bob', 'display_name' => 'Bob']);

    $follows = app(ManageFollows::class);
    $follows->follow($alice, $bob);
    $follows->follow($bob, $alice);

    expect($alice->follows($bob))->toBeTrue()
        ->and($bob->follows($alice))->toBeTrue();

    $manage = app(ManageBlock::class);
    $block = $manage->block($alice, $bob);

    expect($block)->toBeInstanceOf(Block::class)
        ->and($alice->fresh()->isBlockedWith($bob))->toBeTrue()
        ->and($bob->fresh()->isBlockedWith($alice))->toBeTrue()
        ->and($alice->fresh()->hasBlocked($bob))->toBeTrue()
        ->and($bob->fresh()->hasBlocked($alice))->toBeFalse()
        ->and($alice->fresh()->follows($bob))->toBeFalse()
        ->and($bob->fresh()->follows($alice))->toBeFalse();
});

it('prevents blocking yourself', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $manage = app(ManageBlock::class);

    $manage->block($alice, $alice);
})->throws(ValidationException::class);

it('hides profiles bidirectionally with 404 when blocked', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    app(ManageBlock::class)->block($alice, $bob);

    // Alice visits Bob's profile -> 404
    $this->actingAs($alice)
        ->get(route('oasis.profil', 'bob'))
        ->assertNotFound();

    // Bob visits Alice's profile -> 404
    $this->actingAs($bob)
        ->get(route('oasis.profil', 'alice'))
        ->assertNotFound();
});

it('hides Tropfen bidirectionally with 404 and removes them from all feeds', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $alicePost = Fixtures::post($alice, ['body' => 'Tropfen von Alice #sommer']);
    $bobPost = Fixtures::post($bob, ['body' => 'Tropfen von Bob #sommer']);

    expect($alicePost->isVisibleTo($bob))->toBeTrue()
        ->and($bobPost->isVisibleTo($alice))->toBeTrue();

    app(ManageBlock::class)->block($alice, $bob);

    // isVisibleTo is false in both directions
    expect($alicePost->fresh()->isVisibleTo($bob))->toBeFalse()
        ->and($bobPost->fresh()->isVisibleTo($alice))->toBeFalse();

    // Direct view is 404
    $this->actingAs($alice)->get(route('network.posts.show', $bobPost))->assertNotFound();
    $this->actingAs($bob)->get(route('network.posts.show', $alicePost))->assertNotFound();

    // Excluded from feeds
    $feed = app(PostFeed::class);

    $aliceSource = $feed->source($alice);
    expect(collect($aliceSource->items())->pluck('id'))->not->toContain($bobPost->id);

    $bobSource = $feed->source($bob);
    expect(collect($bobSource->items())->pluck('id'))->not->toContain($alicePost->id);

    $aliceDiscover = $feed->discover($alice);
    expect(collect($aliceDiscover->items())->pluck('id'))->not->toContain($bobPost->id);

    $bobDiscover = $feed->discover($bob);
    expect(collect($bobDiscover->items())->pluck('id'))->not->toContain($alicePost->id);

    $taggedForAlice = $feed->tagged('sommer', $alice);
    expect(collect($taggedForAlice->items())->pluck('id'))->not->toContain($bobPost->id);

    $matchingForBob = $feed->matching('Alice', $bob);
    expect(collect($matchingForBob->items())->pluck('id'))->not->toContain($alicePost->id);
});

it('excludes replies from blocked accounts', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $charlie = Fixtures::member(['username' => 'charlie']);

    $post = Fixtures::post($charlie, ['body' => 'Gemeinsamer Tropfen']);
    $replyByBob = Fixtures::post($bob, ['body' => 'Antwort von Bob', 'reply_to_id' => $post->id]);

    $feed = app(PostFeed::class);
    expect($feed->replies($post, $alice)->pluck('id'))->toContain($replyByBob->id);

    app(ManageBlock::class)->block($alice, $bob);

    expect($feed->replies($post, $alice)->pluck('id'))->not->toContain($replyByBob->id);
});

it('prevents following a blocked account', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    app(ManageBlock::class)->block($alice, $bob);

    $follows = app(ManageFollows::class);

    expect(fn () => $follows->follow($alice, $bob))->toThrow(ValidationException::class);
    expect(fn () => $follows->follow($bob, $alice))->toThrow(ValidationException::class);
});

it('prevents interacting (reacting, replying, carrying) on posts of blocked accounts', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $bobPost = Fixtures::post($bob, ['body' => 'Bobs Tropfen']);

    app(ManageBlock::class)->block($alice, $bob);

    expect(Gate::forUser($alice)->allows('interact', $bobPost))->toBeFalse();

    // Trying to react -> 403
    $this->actingAs($alice)
        ->post(route('network.reactions.store', $bobPost))
        ->assertForbidden();

    // Trying to carry -> 403
    $this->actingAs($alice)
        ->get(route('network.posts.carry', $bobPost))
        ->assertForbidden();

    // Trying to reply via PostRequest -> 403
    $this->actingAs($alice)
        ->post(route('network.posts.store'), [
            'body' => 'Unerwünschte Antwort',
            'visibility' => 'public',
            'reply_to' => $bobPost->id,
        ])
        ->assertForbidden();
});

it('prevents changing oasis roles for blocked accounts', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $oasis = Fixtures::oasis($alice);
    app(ManageFollows::class)->follow($bob, $oasis);

    app(ManageBlock::class)->block($alice, $bob);

    $manageOasis = app(ManageOasis::class);

    $this->actingAs($alice);
    expect(fn () => $manageOasis->setRole($oasis, $bob, OasisRole::Host))
        ->toThrow(ValidationException::class);
});

it('prevents starting a chat with a blocked account returning 422', function (): void {
    [$alice] = ChatFixtures::user();
    [$bob] = ChatFixtures::user();

    app(ManageBlock::class)->block($alice, $bob);

    // Alice tries to start a chat with Bob
    $this->actingAs($alice)
        ->postJson(route('chat.api.conversations.store'), [
            'type' => 'direct',
            'member_ids' => [$bob->id],
            'lifetime_days' => 7,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('member_ids');

    // Bob tries to start a chat with Alice
    $this->actingAs($bob)
        ->postJson(route('chat.api.conversations.store'), [
            'type' => 'direct',
            'member_ids' => [$alice->id],
            'lifetime_days' => 7,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('member_ids');
});

it('unblocks an account and restores profile and post visibility', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $bobPost = Fixtures::post($bob, ['body' => 'Bobs Tropfen']);

    $manage = app(ManageBlock::class);
    $manage->block($alice, $bob);

    expect($alice->fresh()->isBlockedWith($bob))->toBeTrue();

    $manage->unblock($alice, $bob);

    expect($alice->fresh()->isBlockedWith($bob))->toBeFalse()
        ->and($bob->fresh()->isBlockedWith($alice))->toBeFalse()
        ->and($bobPost->fresh()->isVisibleTo($alice))->toBeTrue();

    $this->actingAs($alice)
        ->get(route('oasis.profil', 'bob'))
        ->assertOk();
});
