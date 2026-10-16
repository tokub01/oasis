<?php

use App\Actions\Safety\ManageBlock;
use App\Livewire\Concerns\InteractsWithPosts;
use App\Models\Bookmark;
use App\Models\Mute;
use App\Models\Reaction;
use App\Models\User;
use Livewire\Component;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as Network;

/** Minimal host for independently exercising the public post-card methods. */
class NetworkPostInteractionsHarness extends Component
{
    use InteractsWithPosts;

    /** The trait owns only a status string; no foreign user information enters the snapshot. */
    public function render(): string
    {
        return '<div><p role="status">{{ $postStatus }}</p></div>';
    }
}

test('post interactions require a verified account on every public method', function (string $method) {
    $post = Network::post(Network::member());
    Livewire::test(NetworkPostInteractionsHarness::class)->call($method, $post->id)->assertForbidden();
    Livewire::actingAs(User::factory()->unverified()->create())->test(NetworkPostInteractionsHarness::class)
        ->call($method, $post->id)->assertForbidden();
})->with(['react', 'unreact', 'bookmark', 'unbookmark', 'blockUser', 'muteUser', 'unmuteUser', 'deletePost', 'removePostFromOasis']);

test('verified readers may withdraw their own reactions and bookmarks but cannot add reactions', function () {
    $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);
    $post = Network::post(Network::member());
    Reaction::create(['user_id' => $user->id, 'post_id' => $post->id]);
    Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);
    $component = Livewire::actingAs($user)->test(NetworkPostInteractionsHarness::class);
    $component->call('unreact', $post->id)->assertSet('postStatus', 'Reaktion zurückgenommen.');
    $component->call('unbookmark', $post->id);
    expect(Reaction::count())->toBe(0);
    expect(Bookmark::count())->toBe(0);
    $component->call('react', $post->id)->assertForbidden();
});

test('post actions recheck the target policy instead of trusting a supplied identifier', function () {
    $post = Network::post(Network::member());
    Livewire::actingAs(Network::member())->test(NetworkPostInteractionsHarness::class)
        ->call('deletePost', $post->id)->assertForbidden();
    expect($post->fresh())->not->toBeNull();
});

test('blocked posts cannot be reacted to or bookmarked', function () {
    $viewer = Network::member();
    $author = Network::member();
    $post = Network::post($author);
    app(ManageBlock::class)->block($author, $viewer);
    Livewire::actingAs($viewer)->test(NetworkPostInteractionsHarness::class)->call('react', $post->id)->assertForbidden();
    Livewire::actingAs($viewer)->test(NetworkPostInteractionsHarness::class)->call('bookmark', $post->id)->assertNotFound();
    expect(Reaction::count())->toBe(0);
    expect(Bookmark::count())->toBe(0);
});

test('post-card actions delegate verified reactions bookmarks blocks and mutes to the domain layer', function () {
    $viewer = Network::member();
    $author = Network::member();
    $post = Network::post($author);
    $component = Livewire::actingAs($viewer)->test(NetworkPostInteractionsHarness::class);
    $component->call('react', $post->id)->call('react', $post->id)->call('bookmark', $post->id)->call('bookmark', $post->id);
    expect(Reaction::count())->toBe(1);
    expect(Bookmark::count())->toBe(1);
    $component->call('muteUser', $author->id)->call('blockUser', $author->id);
    expect($viewer->fresh()->isBlockedWith($author))->toBeTrue();
    expect(Mute::where('user_id', $viewer->id)->where('muted_user_id', $author->id)->exists())->toBeTrue();
});
