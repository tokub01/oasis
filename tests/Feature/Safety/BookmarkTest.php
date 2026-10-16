<?php

use App\Actions\Network\DeletePost;
use App\Actions\Network\ManageOasis;
use App\Actions\Safety\ManageBlock;
use App\Actions\Safety\ManageBookmark;
use App\Actions\Safety\ManageMute;
use App\Livewire\Safety\Bookmarks;
use App\Models\Bookmark;
use App\Models\Post;
use App\Queries\Safety\BookmarkList;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as Fixtures;

it('bookmarks a Tropfen and removes the bookmark', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $post = Fixtures::post($bob, ['body' => 'Bobs lesenswerter Tropfen']);

    $manage = app(ManageBookmark::class);

    $bookmark = $manage->bookmark($alice, $post);

    expect($bookmark)->toBeInstanceOf(Bookmark::class)
        ->and($alice->fresh()->hasBookmarked($post))->toBeTrue()
        ->and($alice->fresh()->bookmarks()->count())->toBe(1);

    $manage->remove($alice, $post);

    expect($alice->fresh()->hasBookmarked($post))->toBeFalse()
        ->and($alice->fresh()->bookmarks()->count())->toBe(0);
});

it('lists bookmarked Tropfen newest bookmark first', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);

    $post1 = Fixtures::post($bob, ['body' => 'Erster Tropfen']);
    $post2 = Fixtures::post($bob, ['body' => 'Zweiter Tropfen']);

    $manage = app(ManageBookmark::class);
    $manage->bookmark($alice, $post1);
    $manage->bookmark($alice, $post2);

    $list = app(BookmarkList::class);
    $results = $list->paginate($alice);

    // post2 bookmarked last, so it appears first
    expect($results->items()[0]->id)->toBe($post2->id)
        ->and($results->items()[1]->id)->toBe($post1->id);
});

it('keeps bookmarks strictly private so authors and others do not see who bookmarked', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $post = Fixtures::post($bob, ['body' => 'Bobs Tropfen']);

    app(ManageBookmark::class)->bookmark($alice, $post);

    // Bob visits his own post card or stats
    expect($bob->hasBookmarked($post))->toBeFalse();

    // The post card shows bookmarked=true for Alice, but false for Bob
    $this->actingAs($alice)
        ->get(route('network.posts.show', $post))
        ->assertOk()
        ->assertSee('Aus Merkliste entfernen');

    $this->actingAs($bob)
        ->get(route('network.posts.show', $post))
        ->assertOk()
        ->assertDontSee('Aus Merkliste entfernen')
        ->assertSee('Tropfen merken');
});

it('excludes unreadable Tropfen (deleted, removed from oasis, blocked, muted) from bookmarks list', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $charlie = Fixtures::member(['username' => 'charlie']);
    $dave = Fixtures::member(['username' => 'dave']);
    $eve = Fixtures::member(['username' => 'eve']);

    $oasis = Fixtures::oasis($alice);

    $normalPost = Fixtures::post($bob, ['body' => 'Normaler Tropfen']);
    $deletedPost = Fixtures::post($bob, ['body' => 'Gelöschter Tropfen']);
    $removedPost = Fixtures::post($charlie, ['body' => 'Entfernter Tropfen', 'oasis_id' => $oasis->id]);
    $blockedPost = Fixtures::post($dave, ['body' => 'Tropfen von Dave']);
    $mutedPost = Fixtures::post($eve, ['body' => 'Tropfen von Eve']);

    $manage = app(ManageBookmark::class);
    $manage->bookmark($alice, $normalPost);
    $manage->bookmark($alice, $deletedPost);
    $manage->bookmark($alice, $removedPost);
    $manage->bookmark($alice, $blockedPost);
    $manage->bookmark($alice, $mutedPost);

    // Delete $deletedPost
    app(DeletePost::class)->handle($deletedPost);

    // Remove $removedPost from oasis
    app(ManageOasis::class)->removePost($removedPost);

    // Block Dave
    app(ManageBlock::class)->block($alice, $dave);

    // Mute Eve
    app(ManageMute::class)->mute($alice, $eve);

    $list = app(BookmarkList::class);
    $results = $list->paginate($alice);

    $ids = collect($results->items())->pluck('id');

    expect($ids)->toContain($normalPost->id)
        ->and($ids)->not->toContain($deletedPost->id)
        ->and($ids)->not->toContain($removedPost->id)
        ->and($ids)->not->toContain($blockedPost->id)
        ->and($ids)->not->toContain($mutedPost->id);
});

it('aborts with 404 when attempting to bookmark an unreadable or blocked post', function (): void {
    $alice = Fixtures::member(['username' => 'alice']);
    $bob = Fixtures::member(['username' => 'bob']);
    $bobPost = Fixtures::post($bob, ['body' => 'Bobs Tropfen']);

    app(ManageBlock::class)->block($alice, $bob);

    Livewire::actingAs($alice)
        ->test(Bookmarks::class)
        ->call('bookmark', $bobPost->id)
        ->assertNotFound();
});
