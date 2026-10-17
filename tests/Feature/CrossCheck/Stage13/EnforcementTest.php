<?php

/*
 * Stage 13 cross-check (Claude reviews Codex): enforcement of the privacy settings at every entry point, written
 * against the brief, not against the implementation. Focus on bypasses: locked composer contexts, the "following"
 * direction, accounts without a stored row, blocks above permissive audiences, guests and search engines.
 */

use App\Actions\Network\ManageFollows;
use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Livewire\Network\Compose;
use App\Livewire\Network\PostCarry;
use App\Models\Block;
use App\Models\Post;
use App\Models\PrivacySetting;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Queries\Search\DirectorySearch;
use App\Support\Notifications\HintPresentation;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

function s13Privacy(User $user, array $values): PrivacySetting
{
    return $user->privacySetting()->updateOrCreate([], $values);
}

describe('replies and carrying', function () {
    it('follows the author\'s direction for "Menschen, denen du folgst": a follower is not enough', function () {
        $author = N::member();
        $followed = N::member();
        $follower = N::member();
        app(ManageFollows::class)->follow($author, $followed);
        app(ManageFollows::class)->follow($follower, $author);
        s13Privacy($author, ['replies_audience' => 'following', 'carry_audience' => 'following']);
        $post = N::post($author, ['body' => 'Nur für Gefolgte']);

        Livewire::actingAs($followed)->test(Compose::class, ['replyToId' => $post->id])->assertOk();
        Livewire::actingAs($followed)->test(Compose::class, ['carriedId' => $post->id])->assertOk();
        Livewire::actingAs($follower)->test(Compose::class, ['replyToId' => $post->id])->assertForbidden();
        Livewire::actingAs($follower)->test(Compose::class, ['carriedId' => $post->id])->assertForbidden();
    });

    it('refuses a reply that was allowed when the composer opened but not any more', function () {
        $author = N::member();
        $post = N::post($author, ['body' => 'Antworten erwünscht']);
        $composer = Livewire::actingAs(N::member())->test(Compose::class, ['replyToId' => $post->id])->set('body', 'Meine Antwort');

        s13Privacy($author, ['replies_audience' => 'nobody']);

        $composer->call('publish')->assertForbidden();
        expect(Post::where('reply_to_id', $post->id)->count())->toBe(0);
    });

    it('keeps reacting possible while replying and carrying are closed, and hides only the closed buttons', function () {
        $author = N::member();
        s13Privacy($author, ['replies_audience' => 'nobody', 'carry_audience' => 'nobody']);
        $post = N::post($author, ['body' => 'Lesen ja, antworten nein']);

        $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()
            ->assertSee('Lesen ja, antworten nein')->assertDontSee(route('network.posts.carry', $post), false)
            ->assertSee('Reagieren');
        Livewire::test(PostCarry::class, ['post' => $post])->assertForbidden();
    });

    it('lets authors always answer and carry their own Tropfen, whatever they chose', function () {
        $author = N::member();
        s13Privacy($author, ['replies_audience' => 'nobody', 'carry_audience' => 'nobody']);
        $post = N::post($author);

        Livewire::actingAs($author)->test(Compose::class, ['replyToId' => $post->id])->assertOk();
        Livewire::actingAs($author)->test(Compose::class, ['carriedId' => $post->id])->assertOk();
    });

    it('uses the members default for accounts that never stored a setting', function () {
        $author = N::member();
        $post = N::post($author);

        expect(PrivacySetting::where('user_id', $author->id)->exists())->toBeFalse();
        Livewire::actingAs(N::member())->test(Compose::class, ['replyToId' => $post->id])->assertOk();
    });
});

describe('profile, karawane and discover', function () {
    it('lets a block win over a public profile', function () {
        $owner = N::member();
        s13Privacy($owner, ['profile_audience' => 'everyone']);
        $blocked = N::member();
        Block::create(['user_id' => $owner->id, 'blocked_user_id' => $blocked->id]);

        $this->get(route('oasis.profil', $owner->username))->assertOk();
        $this->actingAs($blocked)->get(route('oasis.profil', $owner->username))->assertNotFound();
    });

    it('shows the profile to guests only for "Alle" and to members only for the members or following circle', function () {
        $owner = N::member();
        $stranger = N::member();

        $this->get(route('oasis.profil', $owner->username))->assertNotFound(); // default: members
        $this->actingAs($stranger)->get(route('oasis.profil', $owner->username))->assertOk();

        s13Privacy($owner, ['profile_audience' => 'following']);
        $this->actingAs($stranger)->get(route('oasis.profil', $owner->username))->assertNotFound();
        app(ManageFollows::class)->follow($owner, $stranger);
        $this->actingAs($stranger)->get(route('oasis.profil', $owner->username))->assertOk();
    });

    it('hides the size of the karawane when the owner says so', function () {
        $owner = N::member(['username' => 'zaehlerin']);
        app(ManageFollows::class)->follow(N::member(), $owner);
        s13Privacy($owner, ['follower_count_audience' => 'nobody', 'profile_audience' => 'everyone']);

        $this->actingAs(N::member())->get(route('oasis.profil', 'zaehlerin'))->assertOk()->assertDontSee('Karawane: 1');
        $this->actingAs($owner)->get(route('oasis.profil', 'zaehlerin'))->assertOk()->assertSee('1');
    });

    it('puts only explicitly public profiles into Entdecken, never accounts without a stored row', function () {
        $public = N::member();
        s13Privacy($public, ['profile_audience' => 'everyone']);
        $default = N::member();
        $publicPost = N::post($public, ['body' => 'öffentlich']);
        $defaultPost = N::post($default, ['body' => 'standard']);

        $ids = app(PostFeed::class)->discover(null)->getCollection()->pluck('id');

        expect($ids)->toContain($publicPost->id)->not->toContain($defaultPost->id);
    });
});

describe('mentions', function () {
    it('renders a refused mention as plain text without a profile link, also for third readers', function () {
        $mentioned = N::member(['username' => 'ruhig']);
        s13Privacy($mentioned, ['mentions_audience' => 'nobody', 'profile_audience' => 'everyone']);
        $post = N::post(N::member(), ['body' => 'Hallo @ruhig, schau mal']);

        $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()
            ->assertSee('@ruhig')->assertDontSee('href="'.route('oasis.profil', 'ruhig').'"', false);
    });

    it('keeps a block above a permissive mention audience', function () {
        $mentioned = N::member(['username' => 'offen']);
        $author = N::member();
        s13Privacy($mentioned, ['mentions_audience' => 'members', 'profile_audience' => 'everyone']);
        Block::create(['user_id' => $mentioned->id, 'blocked_user_id' => $author->id]);
        $post = N::post($author, ['body' => 'Hey @offen']);

        $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()
            ->assertDontSee('href="'.route('oasis.profil', 'offen').'"', false);
    });
});

describe('search and search engines', function () {
    it('hides an opted-out member from the people search even for an exact username', function () {
        $hidden = N::member(['username' => 'versteckt', 'display_name' => 'Ver Steckt']);
        s13Privacy($hidden, ['searchable' => false]);

        $results = app(DirectorySearch::class)->people('versteckt', N::member());

        expect($results->pluck('id'))->not->toContain($hidden->id);
    });

    it('tells search engines not to index public pages unless the author allows it', function () {
        $author = N::member(['username' => 'indexfrei']);
        s13Privacy($author, ['profile_audience' => 'everyone', 'search_engines' => false]);
        $post = N::post($author, ['body' => 'öffentlicher Tropfen']);

        $this->get(route('oasis.profil', 'indexfrei'))->assertOk()->assertSee('name="robots" content="noindex', false);
        $this->get(route('network.posts.show', $post))->assertOk()->assertSee('name="robots" content="noindex', false);

        s13Privacy($author, ['search_engines' => true]);
        $this->get(route('oasis.profil', 'indexfrei'))->assertOk()->assertDontSee('name="robots" content="noindex', false);
    });
});

describe('PrivacySetting::allows with preloaded follows', function () {
    it('answers correctly for a viewer the preloaded relation was not restricted to (finding S1)', function () {
        $owner = N::member();
        $viewerA = N::member();
        $viewerB = N::member();
        app(ManageFollows::class)->follow($owner, $viewerB);
        s13Privacy($owner, ['replies_audience' => 'following']);

        // Preloaded the way PostFeed::withCardData does it, restricted to viewer A.
        $settings = PrivacySetting::where('user_id', $owner->id)->with(['user.following' => fn ($following) => $following
            ->where('followable_type', $viewerA->getMorphClass())->where('followable_id', $viewerA->id)])->sole();

        // An unregistered partial relation must not decide anything about B.
        expect($settings->allows('replies_audience', $viewerB))->toBeTrue()
            ->and($owner->privacy()->allows('replies_audience', $viewerB))->toBeTrue();
    });
});

it('uses known follows only for checked IDs and keeps the source settings untouched', function () {
    $owner = N::member();
    $denied = N::member();
    $known = N::member();
    $unknown = N::member();
    $other = N::member();
    app(ManageFollows::class)->follow($owner, $known);
    app(ManageFollows::class)->follow($owner, $unknown);
    $settings = s13Privacy($owner, ['replies_audience' => 'following']);
    $prepared = $settings->withKnownFollows([$denied->id, $known->id], [$known->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($prepared->allows('replies_audience', $denied))->toBeFalse()
        ->and($prepared->allows('replies_audience', $known))->toBeTrue();
    expect(DB::getQueryLog())->toBeEmpty();
    expect($prepared->allows('replies_audience', $unknown))->toBeTrue()
        ->and($prepared->allows('replies_audience', $other))->toBeFalse();
    expect(DB::getQueryLog())->toHaveCount(2);
    DB::flushQueryLog();
    expect($settings->allows('replies_audience', $known))->toBeTrue();
    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();
});

it('does not change the signed-in user or its privacy settings when preparing hint audiences', function () {
    $viewer = N::member();
    $actorA = N::member();
    $actorB = N::member();
    app(ManageFollows::class)->follow($viewer, $actorA);
    app(ManageFollows::class)->follow($viewer, $actorB);
    s13Privacy($viewer, ['mentions_audience' => 'following', 'replies_audience' => 'following']);
    $sender = app(SendHint::class);
    $sender->handle($viewer, $actorA, NoticeKind::Mention, 's1:a', N::post($actorA));
    $sender->handle($viewer, $actorB, NoticeKind::Mention, 's1:b', N::post($actorB));
    $this->actingAs($viewer);
    $settings = $viewer->privacy();
    $hints = $viewer->notifications()->get()->keyBy('data.actor_id');
    $presentation = app(HintPresentation::class);

    expect($presentation->forMany([$hints[$actorA->id]], $viewer))->toHaveCount(1);
    expect($viewer->privacy())->toBe($settings);
    expect($settings->relationLoaded('user'))->toBeFalse()
        ->and($viewer->relationLoaded('following'))->toBeFalse()
        ->and($settings->allows('replies_audience', $actorB))->toBeTrue();
    expect($presentation->forMany([$hints[$actorB->id]], $viewer))->toHaveCount(1);
    expect($settings->allows('replies_audience', $actorA))->toBeTrue();
});
