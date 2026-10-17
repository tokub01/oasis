<?php

use App\Actions\Network\UpdateProfile;
use App\Livewire\Network\Compose;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Queries\Search\DirectorySearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('enforces the profile audience for guests, members, chosen people and owners', function (string $audience, bool $guest, bool $member, bool $chosen) {
    $owner = N::member();
    $viewer = N::member();
    $unverified = User::factory()->unverified()->create();
    $owner->privacySetting()->create(['profile_audience' => $audience]);
    $profile = $owner->profileOrDefault();

    expect($profile->isVisibleTo(null))->toBe($guest)
        ->and($profile->isVisibleTo($viewer))->toBe($member)
        ->and($profile->isVisibleTo($owner))->toBeTrue()
        ->and($profile->isVisibleTo($unverified))->toBe($guest);
    Follow::create(['follower_id' => $viewer->id, 'followable_type' => $owner->getMorphClass(), 'followable_id' => $owner->id]);
    expect($profile->isVisibleTo($viewer))->toBe($member);
    Follow::create(['follower_id' => $owner->id, 'followable_type' => $viewer->getMorphClass(), 'followable_id' => $viewer->id]);
    expect($profile->isVisibleTo($viewer))->toBe($chosen);
})->with(['everyone' => ['everyone', true, true, true], 'members' => ['members', false, true, true],
    'following' => ['following', false, false, true], 'nobody' => ['nobody', false, false, false]]);

it('keeps blocks above public profile and reply and carry permission', function (bool $reverse) {
    $owner = N::member();
    $viewer = N::member();
    $owner->privacySetting()->create(['profile_audience' => 'everyone', 'replies_audience' => 'everyone', 'carry_audience' => 'everyone']);
    $post = N::post($owner);
    Block::create(['user_id' => $reverse ? $owner->id : $viewer->id, 'blocked_user_id' => $reverse ? $viewer->id : $owner->id]);

    expect($owner->profileOrDefault()->isVisibleTo($viewer))->toBeFalse()
        ->and(Gate::forUser($viewer)->allows('reply', $post))->toBeFalse()
        ->and(Gate::forUser($viewer)->allows('carry', $post))->toBeFalse();
    $this->actingAs($viewer)->get(route('oasis.profil', $owner->username))->assertNotFound();
})->with([false, true]);

it('restricts reply and carry independently without restricting reactions', function (string $audience, bool $member, bool $chosen) {
    $owner = N::member();
    $viewer = N::member();
    $owner->privacySetting()->create(['replies_audience' => $audience, 'carry_audience' => $audience]);
    $post = N::post($owner);

    foreach (['reply', 'carry'] as $ability) {
        expect(Gate::forUser($viewer)->allows($ability, $post))->toBe($member)
            ->and(Gate::forUser($owner)->allows($ability, $post))->toBeTrue()
            ->and(Gate::forUser(null)->allows($ability, $post))->toBeFalse();
    }
    expect(Gate::forUser($viewer)->allows('interact', $post))->toBeTrue();
    Follow::create(['follower_id' => $owner->id, 'followable_type' => $viewer->getMorphClass(), 'followable_id' => $viewer->id]);
    foreach (['reply', 'carry'] as $ability) {
        expect(Gate::forUser($viewer)->allows($ability, $post))->toBe($chosen);
    }
})->with(['everyone' => ['everyone', true, true], 'members' => ['members', true, true],
    'following' => ['following', false, true], 'nobody' => ['nobody', false, false]]);

it('hides forbidden buttons and composers and rejects direct composer endpoints', function () {
    $owner = N::member();
    $viewer = N::member();
    $owner->privacySetting()->create(['replies_audience' => 'nobody', 'carry_audience' => 'nobody']);
    $post = N::post($owner);
    Follow::create(['follower_id' => $owner->id, 'followable_type' => $viewer->getMorphClass(), 'followable_id' => $viewer->id]);
    Follow::create(['follower_id' => $viewer->id, 'followable_type' => $owner->getMorphClass(), 'followable_id' => $owner->id]);

    $this->actingAs($viewer)->get(route('network.posts.show', $post))->assertOk()
        ->assertDontSee('quick-reply-')->assertDontSee('wire:submit="publish"', false)
        ->assertDontSee(route('network.posts.carry', $post))->assertSee('Reagieren');
    $this->get(route('network.posts.carry', $post))->assertForbidden();
    NetworkActions::publish(['body' => 'Verbotene Antwort', 'reply_to' => $post->id], $viewer)->assertForbidden();
    NetworkActions::publish(['body' => 'Verbotenes Zitat', 'carried' => $post->id], $viewer)->assertForbidden();
    expect(Post::count())->toBe(1);
});

it('rechecks reply privacy after a composer has been opened', function () {
    $owner = N::member();
    $viewer = N::member();
    $setting = $owner->privacySetting()->create();
    $post = N::post($owner);
    $composer = Livewire::actingAs($viewer)->test(Compose::class, ['replyToId' => $post->id])->set('body', 'Zu spät');
    $setting->update(['replies_audience' => 'nobody']);

    $composer->call('publish')->assertForbidden();
    expect(Post::count())->toBe(1);
});

it('uses privacy defaults without writing a row and only discovers explicitly public profiles', function () {
    $owner = N::member();
    $public = N::member();
    $public->privacySetting()->create(['profile_audience' => 'everyone']);
    N::post($owner);
    $visible = N::post($public);

    expect($owner->profileOrDefault()->isVisibleTo(null))->toBeFalse()
        ->and($owner->profileOrDefault()->isVisibleTo(N::member()))->toBeTrue()
        ->and(app(PostFeed::class)->discover(null)->pluck('id')->all())->toBe([$visible->id]);
    Livewire::actingAs($owner)->test(Compose::class)->assertSet('visibility', 'members');
    $this->assertDatabaseMissing('privacy_settings', ['user_id' => $owner->id]);
    $owner->privacySetting()->create(['default_post_visibility' => 'public', 'profile_audience' => 'following']);
    Livewire::actingAs($owner->fresh())->test(Compose::class)->assertSet('visibility', 'public');
});

it('shows the follower count only to its configured audience', function (string $audience, bool $guest, bool $member, bool $chosen) {
    $owner = N::member();
    $viewer = N::member();
    $owner->privacySetting()->create(['profile_audience' => 'everyone', 'follower_count_audience' => $audience]);
    $url = route('oasis.profil', $owner->username);

    expect(str_contains($this->get($url)->getContent(), 'Karawane:'))->toBe($guest);
    expect(str_contains($this->actingAs($viewer)->get($url)->assertOk()->getContent(), 'Karawane:'))->toBe($member);
    Follow::create(['follower_id' => $owner->id, 'followable_type' => $viewer->getMorphClass(), 'followable_id' => $viewer->id]);
    expect(str_contains($this->get($url)->assertOk()->getContent(), 'Karawane:'))->toBe($chosen);
    $this->actingAs($owner)->get($url)->assertOk()->assertSee('Karawane:');
})->with(['everyone' => ['everyone', true, true, true], 'members' => ['members', false, true, true],
    'following' => ['following', false, false, true], 'nobody' => ['nobody', false, false, false]]);

it('excludes nonsearchable accounts before the directory result limit', function () {
    $viewer = N::member();
    $hidden = N::member(['username' => 'privacy_hidden']);
    $visible = N::member(['username' => 'privacy_visible']);
    $hidden->privacySetting()->create(['searchable' => false]);

    expect(app(DirectorySearch::class)->people('privacy_', $viewer)->pluck('id')->all())->toBe([$visible->id]);
});

it('indexes guest pages only when both the profile audience and search-engine choice allow it', function (string $audience, bool $searchEngines, bool $indexed) {
    $owner = N::member();
    $owner->privacySetting()->create(['profile_audience' => $audience, 'search_engines' => $searchEngines]);
    $post = N::post($owner);

    $profile = $this->get(route('oasis.profil', $owner->username))->assertStatus($audience === 'everyone' ? 200 : 404);
    expect(str_contains($profile->getContent(), '<meta name="robots" content="noindex, nofollow">'))->toBe(! $indexed);
    expect(str_contains($this->get(route('network.posts.show', $post))->assertOk()->getContent(),
        '<meta name="robots" content="noindex, nofollow">'))->toBe(! $indexed);
})->with([
    'public and opted in' => ['everyone', true, true],
    'public but opted out' => ['everyone', false, false],
    'members and opted in' => ['members', true, false],
    'members and opted out' => ['members', false, false],
    'following and opted in' => ['following', true, false],
    'following and opted out' => ['following', false, false],
]);

it('checks Following audiences on a batch of cards without per-card queries', function () {
    $viewer = N::member();
    $authors = User::factory()->count(5)->create();
    foreach ($authors as $author) {
        $author->privacySetting()->create(['replies_audience' => 'following', 'carry_audience' => 'following']);
        Follow::create(['follower_id' => $author->id, 'followable_type' => $viewer->getMorphClass(), 'followable_id' => $viewer->id]);
        $original = N::post($author);
        N::post($author, ['carried_post_id' => $original->id]);
    }
    $chosen = $authors->take(4)->pluck('id')->all();
    Follow::where('follower_id', $authors->last()->id)->delete();
    $viewer->blockedUserIds();
    $posts = app(PostFeed::class)->withCardData(Post::query(), $viewer)->get();
    DB::enableQueryLog();
    DB::flushQueryLog();

    foreach ($posts as $post) {
        expect(Gate::forUser($viewer)->allows('reply', $post))->toBe(in_array($post->user_id, $chosen, true))
            ->and(Gate::forUser($viewer)->allows('carry', $post))->toBe(in_array($post->user_id, $chosen, true));
        if ($post->carried !== null) {
            expect(Gate::forUser($viewer)->allows('carry', $post->carried))->toBe(in_array($post->user_id, $chosen, true));
        }
    }
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('keeps profile editing separate from privacy and ignores injected audience fields', function () {
    $owner = N::member();
    $owner->privacySetting()->create(['profile_audience' => 'following', 'default_post_visibility' => 'members']);

    $this->actingAs($owner)->get(route('network.profile.edit'))->assertOk()->assertSee(route('app.privacy'))
        ->assertSee('Privatsphäre-Einstellungen')->assertDontSee('wire:model="visibility"', false);
    app(UpdateProfile::class)->handle($owner, ['bio' => 'Neuer Text', 'avatar_color' => 'sun',
        'visibility' => 'public', 'profile_audience' => 'everyone']);
    expect($owner->fresh()->profile->bio)->toBe('Neuer Text');
    $this->assertDatabaseHas('privacy_settings', ['user_id' => $owner->id, 'profile_audience' => 'following', 'default_post_visibility' => 'members']);
});
