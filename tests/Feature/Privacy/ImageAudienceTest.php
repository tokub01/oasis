<?php

/* "Wessen Bilder siehst du sofort?": covered images are not even loaded until tapped; default = people you follow. */

use App\Livewire\Privacy\Center;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Support\Privacy\PrivacyOptions;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

function postWithImage(User $author): Post
{
    $post = N::post($author, ['visibility' => 'public']);
    $post->images()->create(['path' => 'network/posts/'.$post->id.'/1.webp', 'alt' => 'Ein Sonnenuntergang', 'width' => 800, 'height' => 600, 'position' => 0]);

    return $post;
}

function imageUrl(Post $post): string
{
    return route('network.images.show', [$post, $post->images->first()->id]);
}

function follow(User $follower, User $followed): void
{
    Follow::create(['follower_id' => $follower->id, 'followable_type' => $followed->getMorphClass(), 'followable_id' => $followed->id]);
}

it('covers images of people the member does not follow and shows those of people they follow', function () {
    $viewer = N::member();
    $stranger = N::member();
    $friend = N::member();
    follow($viewer, $friend);
    $strangers = postWithImage($stranger);
    $friends = postWithImage($friend);

    $this->actingAs($viewer)->get(route('network.posts.show', $strangers))->assertOk()
        ->assertSee('Bild anzeigen')->assertSee('data-src="'.imageUrl($strangers->fresh()).'"', false)
        ->assertDontSee(' src="'.imageUrl($strangers->fresh()).'"', false);
    $this->get(route('network.posts.show', $friends))->assertOk()
        ->assertDontSee('Bild anzeigen')->assertSee(' src="'.imageUrl($friends->fresh()).'"', false);
});

it('follows the chosen setting, always shows own images and shows guests public images', function () {
    $viewer = N::member();
    $stranger = N::member();
    $post = postWithImage($stranger);
    $own = postWithImage($viewer);

    $viewer->privacySetting()->create(['images_audience' => 'members']);
    $this->actingAs($viewer)->get(route('network.posts.show', $post))->assertDontSee('Bild anzeigen');
    $viewer->privacySetting()->update(['images_audience' => 'nobody']);
    $this->actingAs($viewer->fresh())->get(route('network.posts.show', $post))->assertSee('Bild anzeigen');
    $this->get(route('network.posts.show', $own))->assertDontSee('Bild anzeigen');

    auth()->logout();
    $this->get(route('network.posts.show', $post))->assertOk()->assertDontSee('Bild anzeigen');
});

it('offers the setting in the privacy center and keeps members under 18 from showing all images', function () {
    Livewire::actingAs(N::member())->test(Center::class)->assertSee('Wessen Bilder siehst du sofort?');

    $minor = N::member(['birth_date' => now()->subYears(15)->toDateString()]);
    expect(array_keys(PrivacyOptions::choices('images_audience', $minor)))->toBe(['contacts', 'following', 'nobody']);
});
