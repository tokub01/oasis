<?php

use App\Actions\Safety\ManageBlock;
use App\Actions\Safety\ManageBookmark;
use App\Actions\Safety\ManageMute;
use Tests\Support\NetworkFixtures as Fixtures;

it('requires authentication for safety pages', function (): void {
    $this->get(route('safety.index'))
        ->assertRedirect(route('login'));

    $this->get(route('safety.bookmarks'))
        ->assertRedirect(route('login'));
});

it('renders safety overview with blocked and muted accounts', function (): void {
    $alice = Fixtures::member(['username' => 'alice', 'display_name' => 'Alice']);
    $bob = Fixtures::member(['username' => 'bob', 'display_name' => 'Bob']);
    $charlie = Fixtures::member(['username' => 'charlie', 'display_name' => 'Charlie']);

    app(ManageBlock::class)->block($alice, $bob);
    app(ManageMute::class)->mute($alice, $charlie);

    $this->actingAs($alice)
        ->get(route('safety.index'))
        ->assertOk()
        ->assertSee('Bob')
        ->assertSee('@bob')
        ->assertSee('Blockade aufheben')
        ->assertSee('Charlie')
        ->assertSee('@charlie')
        ->assertSee('Nicht mehr stummschalten');
});

it('allows unblocking via POST /app/sicherheit/block/{user} DELETE', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob', 'display_name' => 'Bob']);

    app(ManageBlock::class)->block($alice, $bob);
    expect($alice->hasBlocked($bob))->toBeTrue();

    $this->actingAs($alice)
        ->delete(route('safety.block.destroy', $bob))
        ->assertSessionHas('status', 'Blockade von Bob aufgehoben.');

    expect($alice->fresh()->hasBlocked($bob))->toBeFalse();
});

it('allows unmuting via POST /app/sicherheit/mute/{user} DELETE', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob', 'display_name' => 'Bob']);

    app(ManageMute::class)->mute($alice, $bob);
    expect($alice->hasMuted($bob))->toBeTrue();

    $this->actingAs($alice)
        ->delete(route('safety.mute.destroy', $bob))
        ->assertSessionHas('status', 'Stummschaltung von Bob aufgehoben.');

    expect($alice->fresh()->hasMuted($bob))->toBeFalse();
});

it('renders empty states on safety page when no one is blocked or muted', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);

    $this->actingAs($alice)
        ->get(route('safety.index'))
        ->assertOk()
        ->assertSee('Du hast aktuell niemanden blockiert.')
        ->assertSee('Du hast aktuell niemanden stummgeschaltet.');
});

it('provides working model relationships for blocks, mutes, and bookmarks', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $charlie = Fixtures::member(['username' => 'charlie']);
    $post = Fixtures::post($charlie);

    $block = app(ManageBlock::class)->block($alice, $bob);
    $mute = app(ManageMute::class)->mute($alice, $bob);
    $bookmark = app(ManageBookmark::class)->bookmark($alice, $post);

    expect($block->blocker->id)->toBe($alice->id)
        ->and($block->blocked->id)->toBe($bob->id)
        ->and($mute->muter->id)->toBe($alice->id)
        ->and($mute->muted->id)->toBe($bob->id)
        ->and($bookmark->user->id)->toBe($alice->id)
        ->and($bookmark->post->id)->toBe($post->id)
        ->and($alice->blocks()->count())->toBe(1)
        ->and($bob->blockedBy()->count())->toBe(1)
        ->and($alice->mutes()->count())->toBe(1)
        ->and($alice->bookmarks()->count())->toBe(1)
        ->and($alice->bookmarkedPosts()->count())->toBe(1)
        ->and($post->bookmarks()->count())->toBe(1)
        ->and($alice->isBlockedWith($alice))->toBeFalse()
        ->and($alice->blockedUserIds(fresh: true))->toBe([$bob->id])
        ->and($alice->mutedUserIds(fresh: true))->toBe([$bob->id]);
});
