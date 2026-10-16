<?php

use App\Actions\Network\ManageFollows;
use App\Livewire\Notifications\Inbox;
use App\Livewire\Safety\Bookmarks;
use App\Livewire\Safety\SafetyOverview;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Mute;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ChatFixtures as Chat;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as Network;

/*
 * Codex' independent stage 8 safety cross-check, derived from stage8-antigravity-brief.md and the team contract.
 * Real HTTP routes and persisted rows exercise both sides of the boundary; Antigravity's tests are not reused.
 */
beforeEach(fn () => $this->withoutVite());

/** Public profiles make discovery/profile assertions prove safety rather than default members-only visibility. */
function safetyCrossCheckMember(string $name): User
{
    $user = Network::member(['username' => 'crosscheck_'.$name, 'display_name' => 'Crosscheck '.$name]);
    $user->profile()->create(['visibility' => 'public']);

    return $user;
}

/** A shared oasis keeps both authors in the Quelle even after a block removes their mutual personal follows. */
function safetyCrossCheckPair(): array
{
    $alpha = safetyCrossCheckMember('alpha');
    $beta = safetyCrossCheckMember('beta');
    $oasis = Network::oasis(safetyCrossCheckMember('neutral'));
    foreach ([$alpha, $beta] as $user) {
        NetworkActions::oasis('follow', $oasis, $user)->assertHasNoErrors();
    }
    $alphaPost = Network::post($alpha, ['body' => 'crosscheck_secret_alpha #Grenzfall', 'oasis_id' => $oasis->id]);
    $betaPost = Network::post($beta, ['body' => 'crosscheck_secret_beta #Grenzfall', 'oasis_id' => $oasis->id]);

    return [$alpha, $beta, $oasis, $alphaPost, $betaPost];
}

/** Use a fresh authenticated model after every safety write; requests must not share a stale safety memo. */
function safetyCrossCheckBlock(User $actor, User $target): void
{
    NetworkActions::post('blockUser', $target->id, $actor->fresh())->assertHasNoErrors();
}

it('removes both personal follows but preserves oasis follows and sends no block notification', function () {
    [$alpha, $beta, $oasis] = safetyCrossCheckPair();
    app(ManageFollows::class)->follow($alpha, $beta);
    app(ManageFollows::class)->follow($beta, $alpha);
    $before = $beta->notifications()->count();
    safetyCrossCheckBlock($alpha, $beta);
    safetyCrossCheckBlock($alpha, $beta);

    expect(Block::where('user_id', $alpha->id)->where('blocked_user_id', $beta->id)->count())->toBe(1);
    expect(Follow::whereIn('follower_id', [$alpha->id, $beta->id])->where('followable_type', $alpha->getMorphClass())->count())->toBe(0);
    expect($oasis->isMember($alpha))->toBeTrue()->and($oasis->isMember($beta))->toBeTrue();
    expect($beta->notifications()->count())->toBe($before);
    expect($alpha->fresh()->isBlockedWith($beta))->toBeTrue()->and($beta->fresh()->isBlockedWith($alpha))->toBeTrue();
    expect($alpha->fresh()->blockedUserIds())->toContain($beta->id);
    expect($beta->fresh()->blockedUserIds())->toContain($alpha->id);
});

it('hides blocked authors in both directions even through a shared oasis', function (string $list) {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $betaPost], [$beta, $alphaPost]] as [$viewer, $hidden]) {
        $url = $list === 'oasis' ? route('network.oases.show', $oasis) : route($list);
        $this->actingAs($viewer->fresh())->get($url)->assertOk()->assertDontSee(explode(' ', $hidden->body)[0]);
    }
})->with(['app.home', 'network.discover', 'oasis']);

it('returns 404 for both profiles and post pages including mixed-case profile handles', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta, $betaPost], [$beta, $alpha, $alphaPost]] as [$viewer, $hidden, $post]) {
        $this->actingAs($viewer->fresh())->get(route('oasis.profil', $hidden->username))->assertNotFound();
        $this->get(route('oasis.profil', strtoupper($hidden->username)))->assertNotFound();
        $this->get(route('network.posts.show', $post))->assertNotFound();
    }
});

it('filters blocked replies in both directions on an otherwise readable third-party Tropfen', function () {
    [$alpha, $beta] = safetyCrossCheckPair();
    $parent = Network::post(safetyCrossCheckMember('thread'));
    $alphaReply = Network::post($alpha, ['body' => 'Antwort Geheim Alpha', 'reply_to_id' => $parent->id]);
    $betaReply = Network::post($beta, ['body' => 'Antwort Geheim Beta', 'reply_to_id' => $parent->id]);
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $alphaReply, $betaReply], [$beta, $betaReply, $alphaReply]] as [$viewer, $own, $hidden]) {
        $this->actingAs($viewer->fresh())->get(route('network.posts.show', $parent))->assertOk()
            ->assertSee($own->body)->assertDontSee($hidden->body);
    }
});

it('rejects replies carries and reactions in both directions without writing any rows', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    $postCount = Post::count();
    foreach ([[$alpha, $betaPost], [$beta, $alphaPost]] as [$viewer, $hidden]) {
        $this->actingAs($viewer->fresh())->get(route('network.posts.carry', $hidden))->assertForbidden();
        foreach (['reply_to', 'carried'] as $field) {
            NetworkActions::publish(['body' => 'Versuch', 'visibility' => 'public', $field => $hidden->id])->assertForbidden();
        }
        NetworkActions::post('react', $hidden->id)->assertForbidden();
    }
    expect(Post::count())->toBe($postCount)->and(Reaction::count())->toBe(0);
});

it('rejects refollowing in both directions at the action and HTTP boundaries', function () {
    [$alpha, $beta] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta], [$beta, $alpha]] as [$viewer, $hidden]) {
        expect(fn () => app(ManageFollows::class)->follow($viewer->fresh(), $hidden->fresh()))->toThrow(ValidationException::class);
        $response = NetworkActions::profile('follow', $hidden, $viewer->fresh());
        $response->assertNotFound();
    }
    expect(Follow::where('followable_type', $alpha->getMorphClass())->count())->toBe(0);
});

it('rejects both direct and group chat creation across a blockade in either direction', function (string $type) {
    [$alpha] = Chat::user();
    [$beta] = Chat::user();
    [$neutral] = Chat::user();
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta], [$beta, $alpha]] as [$viewer, $hidden]) {
        $members = $type === 'direct' ? [$hidden->id] : [$hidden->id, $neutral->id];
        $this->actingAs($viewer->fresh())->postJson('/app/chat/api/conversations', [
            'type' => $type, 'member_ids' => $members, 'lifetime_days' => 7,
        ])->assertUnprocessable();
    }
    expect(Conversation::count())->toBe(0);
})->with(['direct', 'group']);

it('hides blocked people and posts from member search and topic lists in both directions', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta, $betaPost], [$beta, $alpha, $alphaPost]] as [$viewer, $hidden, $post]) {
        $this->actingAs($viewer->fresh())->get(route('search.index', ['q' => 'crosscheck']))->assertOk()
            ->assertDontSee($hidden->username)->assertDontSee($post->body);
        $this->get(route('search.tags.show', 'grenzfall'))->assertOk()->assertDontSee(explode(' ', $post->body)[0]);
    }
});

it('hides existing hints and suppresses new mentions across either side of a block', function () {
    [$alpha, $beta, $oasis] = safetyCrossCheckPair();
    foreach ([[$alpha, $beta], [$beta, $alpha]] as [$author, $recipient]) {
        NetworkActions::publish([
            'body' => 'Vor Block @'.$recipient->username, 'visibility' => 'public',
        ], $author)->assertHasNoErrors();
    }
    expect($alpha->notifications()->count())->toBe(1)->and($beta->notifications()->count())->toBe(1);
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta], [$beta, $alpha]] as [$viewer, $hidden]) {
        $this->actingAs($viewer->fresh())->get(route('notifications.index'))->assertOk()->assertDontSee('Vor Block');
        Livewire::actingAs($viewer->fresh())->test(Inbox::class)->assertViewHas('groups', []);
        NetworkActions::publish(['body' => 'Nach Block @'.$hidden->username, 'visibility' => 'public'])->assertHasNoErrors();
    }
    expect($alpha->notifications()->count())->toBe(1)->and($beta->notifications()->count())->toBe(1);
});

it('refuses host role changes across a block in both directions', function () {
    [$alpha, $beta, $oasis] = safetyCrossCheckPair();
    $oasis->members()->updateExistingPivot($alpha->id, ['role' => 'host']);
    $oasis->members()->updateExistingPivot($beta->id, ['role' => 'host']);
    safetyCrossCheckBlock($alpha, $beta);
    foreach ([[$alpha, $beta], [$beta, $alpha]] as [$viewer, $hidden]) {
        NetworkActions::role($oasis, $hidden, 'member', $viewer->fresh())->assertHasErrors('role');
        expect($oasis->isHost($hidden))->toBeTrue();
    }
});

it('keeps another accounts block intact and restores access only after both blocks are lifted', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    safetyCrossCheckBlock($alpha, $beta);
    Livewire::actingAs($beta->fresh())->test(SafetyOverview::class)->call('unblock', $alpha->id)->assertHasNoErrors();
    expect($alpha->fresh()->isBlockedWith($beta))->toBeTrue();
    safetyCrossCheckBlock($beta, $alpha);
    Livewire::actingAs($alpha->fresh())->test(SafetyOverview::class)->call('unblock', $beta->id)->assertHasNoErrors();
    $this->actingAs($alpha->fresh())->get(route('network.posts.show', $betaPost))->assertNotFound();
    Livewire::actingAs($beta->fresh())->test(SafetyOverview::class)->call('unblock', $alpha->id)->assertHasNoErrors();
    $this->actingAs($alpha->fresh())->get(route('network.posts.show', $betaPost))->assertOk();
    expect(Follow::where('followable_type', $alpha->getMorphClass())->count())->toBe(0);
});

it('mutes only the callers lists and replies while leaving the profile readable and the other side unchanged', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    $parent = Network::post(safetyCrossCheckMember('mute_thread'));
    $reply = Network::post($beta, ['body' => 'Stumme Antwort', 'reply_to_id' => $parent->id]);
    $before = $beta->notifications()->count();
    NetworkActions::post('muteUser', $beta->id, $alpha)->assertHasNoErrors();
    NetworkActions::post('muteUser', $beta->id, $alpha)->assertHasNoErrors();
    expect(Mute::count())->toBe(1)->and($beta->notifications()->count())->toBe($before);
    expect($alpha->fresh()->mutedUserIds())->toContain($beta->id)->and($beta->fresh()->mutedUserIds())->toBe([]);
    expect($alpha->fresh()->isBlockedWith($beta))->toBeFalse();
    $this->actingAs($alpha->fresh())->get(route('oasis.profil', $beta->username))->assertOk();
    foreach ([route('app.home'), route('network.discover'), route('network.oases.show', $oasis)] as $url) {
        $this->actingAs($alpha->fresh())->get($url)->assertOk()->assertDontSee('crosscheck_secret_beta');
        $this->actingAs($beta->fresh())->get($url)->assertOk()->assertSee('crosscheck_secret_alpha');
    }
    $this->actingAs($alpha->fresh())->get(route('network.posts.show', $parent))->assertOk()->assertDontSee($reply->body);
    Livewire::actingAs($beta->fresh())->test(SafetyOverview::class)->call('unmute', $alpha->id)->assertHasNoErrors();
    expect(Mute::count())->toBe(1);
    Livewire::actingAs($alpha->fresh())->test(SafetyOverview::class)->call('unmute', $beta->id)->assertHasNoErrors();
    $this->actingAs($alpha->fresh())->get(route('network.oases.show', $oasis))->assertOk()->assertSee('crosscheck_secret_beta');
});

it('keeps bookmarks private idempotent and undeletable by another account', function () {
    $owner = safetyCrossCheckMember('bookmark_owner');
    $author = safetyCrossCheckMember('bookmark_author');
    $post = Network::post($author, ['body' => 'Nur in meiner Merkliste']);
    $before = $author->notifications()->count();
    Livewire::actingAs($owner)->test(Bookmarks::class)->call('bookmark', $post->id)->assertHasNoErrors();
    Livewire::actingAs($owner)->test(Bookmarks::class)->call('bookmark', $post->id)->assertHasNoErrors();
    expect(Bookmark::count())->toBe(1)->and($author->notifications()->count())->toBe($before);
    $this->actingAs($owner)->get(route('safety.bookmarks'))->assertOk()->assertSee($post->body);
    $this->actingAs($author)->get(route('safety.bookmarks', ['user_id' => $owner->id]))->assertOk()->assertDontSee($post->body);
    Livewire::actingAs($author)->test(Bookmarks::class)->call('unbookmark', $post->id)->assertHasNoErrors();
    expect(Bookmark::where('user_id', $owner->id)->where('post_id', $post->id)->count())->toBe(1);
    Livewire::actingAs($owner)->test(Bookmarks::class)->call('unbookmark', $post->id)->assertHasNoErrors();
    expect(Bookmark::count())->toBe(0);
});

it('hides bookmarks after deletion removal or a blockade', function (string $change) {
    $owner = safetyCrossCheckMember('bookmark_reader');
    $author = safetyCrossCheckMember('bookmark_hidden');
    $post = Network::post($author, ['body' => 'Verborgene Merkliste '.$change]);
    Livewire::actingAs($owner)->test(Bookmarks::class)->call('bookmark', $post->id)->assertHasNoErrors();
    match ($change) {
        'deleted' => $post->delete(),
        'removed' => $post->forceFill(['removed_from_oasis_at' => now()])->save(),
        'blocked-by-owner' => safetyCrossCheckBlock($owner, $author),
        'blocked-by-author' => safetyCrossCheckBlock($author, $owner),
    };
    $this->actingAs($owner->fresh())->get(route('safety.bookmarks'))->assertOk()->assertDontSee($post->body);
})->with(['deleted', 'removed', 'blocked-by-owner', 'blocked-by-author']);

it('cannot forge safety or bookmark ownership with submitted user IDs', function () {
    $caller = safetyCrossCheckMember('caller');
    $victim = safetyCrossCheckMember('victim');
    $target = safetyCrossCheckMember('target');
    $post = Network::post($target);
    NetworkActions::post('blockUser', $target->id, $caller)->assertHasNoErrors();
    NetworkActions::post('muteUser', $target->id, $caller)->assertHasNoErrors();
    expect(Block::where('user_id', $victim->id)->count())->toBe(0)->and(Mute::where('user_id', $victim->id)->count())->toBe(0);
    Livewire::actingAs($caller)->test(SafetyOverview::class)->call('unblock', $target->id)->assertHasNoErrors();
    Livewire::actingAs($caller->fresh())->test(Bookmarks::class)->call('bookmark', $post->id)->assertHasNoErrors();
    expect(Bookmark::where('user_id', $victim->id)->count())->toBe(0);
    expect(Bookmark::where('user_id', $caller->id)->count())->toBe(1);
});

it('requires login and verified email for private safety lists and bookmark writes', function () {
    $post = Network::post(Network::member());
    foreach (['safety.index', 'safety.bookmarks'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
    Livewire::test(Bookmarks::class)->assertForbidden();
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get(route('safety.index'))->assertRedirect(route('verification.notice'));
    $this->get(route('safety.bookmarks'))->assertRedirect(route('verification.notice'));
    Livewire::actingAs($unverified)->test(Bookmarks::class)->assertForbidden();
});

it('does not reveal a blocked original inside a third parties carried Tropfen', function () {
    [$alpha, $beta, $oasis, $alphaPost, $betaPost] = safetyCrossCheckPair();
    $third = safetyCrossCheckMember('carrier');
    $carry = Network::post($third, ['body' => 'Lesbarer Kommentar', 'carried_post_id' => $betaPost->id]);
    safetyCrossCheckBlock($alpha, $beta);
    $this->actingAs($alpha->fresh())->get(route('network.posts.show', $carry))->assertOk()
        ->assertSee('Lesbarer Kommentar')->assertDontSee('crosscheck_secret_beta')
        ->assertDontSee('href="'.route('oasis.profil', $beta->username).'"', false);
});

it('rejects attempts to bookmark a currently unreadable Tropfen without storing a row', function (string $change) {
    $owner = safetyCrossCheckMember('bookmark_attempt');
    $author = safetyCrossCheckMember('bookmark_unreadable');
    $post = Network::post($author);
    if ($change === 'removed') {
        $post->forceFill(['removed_from_oasis_at' => now()])->save();
    } else {
        safetyCrossCheckBlock($author, $owner);
    }
    $component = Livewire::actingAs($owner->fresh())->test(Bookmarks::class)->call('bookmark', $post->id);
    $component->assertNotFound();
    expect(Bookmark::where('user_id', $owner->id)->count())->toBe(0);
})->with(['removed', 'blocked']);

it('keeps muted mention hints one-sided without notifying anyone about the mute itself', function () {
    $alpha = safetyCrossCheckMember('mention_alpha');
    $beta = safetyCrossCheckMember('mention_beta');
    NetworkActions::post('muteUser', $beta->id, $alpha)->assertHasNoErrors();
    expect($alpha->notifications()->count())->toBe(0)->and($beta->notifications()->count())->toBe(0);
    NetworkActions::publish([
        'body' => 'Hallo @'.$alpha->username, 'visibility' => 'public',
    ], $beta->fresh())->assertHasNoErrors();
    NetworkActions::publish([
        'body' => 'Hallo @'.$beta->username, 'visibility' => 'public',
    ], $alpha->fresh())->assertHasNoErrors();
    expect($alpha->notifications()->count())->toBe(0)->and($beta->notifications()->count())->toBe(1);
});

it('prevents a third-party group creator from putting a blocked pair in the same new chat', function () {
    [$alpha] = Chat::user();
    [$beta] = Chat::user();
    [$creator] = Chat::user();
    safetyCrossCheckBlock($alpha, $beta);
    $this->actingAs($creator)->postJson('/app/chat/api/conversations', [
        'type' => 'group', 'member_ids' => [$alpha->id, $beta->id], 'lifetime_days' => 7,
    ])->assertUnprocessable();
    expect(Conversation::count())->toBe(0);
});

it('rejects adding a blocked peer of an existing group member even when the owner is neutral', function () {
    [$alpha] = Chat::user();
    [$beta] = Chat::user();
    [$creator] = Chat::user();
    $response = $this->actingAs($creator)->postJson('/app/chat/api/conversations', [
        'type' => 'group', 'member_ids' => [$alpha->id], 'lifetime_days' => 7,
    ])->assertCreated();
    $id = $response->json('data.id');
    safetyCrossCheckBlock($alpha, $beta);
    $this->actingAs($creator->fresh())->postJson('/app/chat/api/conversations/'.$id.'/members', ['user_id' => $beta->id])
        ->assertUnprocessable();
    expect(Conversation::findOrFail($id)->activeMembers()->where('user_id', $beta->id)->exists())->toBeFalse();
});

it('renders twenty foreign cards without twenty separate mute lookups', function () {
    $viewer = safetyCrossCheckMember('query_viewer');
    $author = safetyCrossCheckMember('query_author');
    foreach (range(1, 20) as $i) {
        Network::post($author, ['body' => 'Abfragegrenze '.$i]);
    }
    $this->actingAs($viewer);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('network.discover'))->assertOk();
    $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from `mutes`'));
    DB::disableQueryLog();
    expect($queries->count())->toBeLessThanOrEqual(2);
});

it('paginates bookmarks by bookmark order without repeating posts when saved order differs from post IDs', function () {
    $viewer = safetyCrossCheckMember('page_reader');
    $author = safetyCrossCheckMember('page_author');
    $posts = collect(range(1, 21))->map(fn ($i) => Network::post($author, ['body' => 'Gemerkter Seiteneintrag '.$i]));
    $this->actingAs($viewer);
    foreach ($posts->reverse() as $post) {
        Livewire::actingAs($viewer)->test(Bookmarks::class)->call('bookmark', $post->id)->assertHasNoErrors();
    }
    $first = Livewire::actingAs($viewer)->test(Bookmarks::class)->viewData('posts');
    $firstIds = collect($first->items())->pluck('id')->all();
    expect($firstIds)->toHaveCount(20);
    expect($first->nextPageUrl())->not->toBeNull();
    $second = Livewire::actingAs($viewer)
        ->withQueryParams(['cursor' => $first->nextCursor()->encode()])
        ->test(Bookmarks::class)
        ->viewData('posts');
    $secondIds = collect($second->items())->pluck('id')->all();
    expect(array_intersect($firstIds, $secondIds))->toBe([]);
    expect($secondIds)->toBe([$posts->last()->id]);
    expect($second->hasMorePages())->toBeFalse();
});
