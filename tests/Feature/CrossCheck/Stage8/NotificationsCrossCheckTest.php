<?php

use App\Actions\Network\ManageFollows;
use App\Livewire\Notifications\Inbox;
use App\Livewire\Notifications\Settings;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/*
 * Claude's independent cross-check of Codex' stage 8 hints and @mentions: edge cases and abuse paths written
 * without looking at Codex' own tests – through the HTTP layer where possible.
 */
beforeEach(fn () => $this->withoutVite());

/** Publish a Tropfen through the real endpoint. */
function publish(User $author, array $input): Post
{
    NetworkActions::publish($input + ['visibility' => 'public'], $author)->assertHasNoErrors();

    return Post::where('user_id', $author->id)->latest('id')->firstOrFail();
}

function hintKinds(User $user): array
{
    return $user->fresh()->notifications->pluck('data.kind')->all();
}

it('ignores e-mail addresses, unknown names and self-mentions', function () {
    $author = N::member(['username' => 'autorin']);
    N::member(['username' => 'mira']);
    publish($author, ['body' => 'Schreib an info@mira.tld oder mira@oasis.tld, @niemand_hier und @autorin']);

    expect(User::where('username', 'mira')->sole()->notifications)->toBeEmpty()
        ->and($author->fresh()->notifications)->toBeEmpty();
});

it('sends one hint per person even if they are replied to and mentioned', function () {
    $mira = N::member(['username' => 'mira']);
    $original = N::post($mira);
    publish(N::member(), ['body' => 'Genau, @mira! Und nochmal @MIRA', 'reply_to' => $original->id]);

    expect(hintKinds($mira))->toBe(['reply']);
});

it('notifies at most ten mentioned people per Tropfen', function () {
    $people = collect(range(1, 12))->map(fn ($i) => N::member(['username' => 'person_'.$i]));
    publish(N::member(), ['body' => $people->map(fn ($p) => '@'.$p->username)->implode(' ')]);

    expect($people->filter(fn ($p) => $p->fresh()->notifications->isNotEmpty())->count())->toBe(10);
});

it('sends nothing across a blockade or to people who muted the author', function () {
    $author = N::member();
    $blocker = N::member(['username' => 'blockt']);
    $muter = N::member(['username' => 'stumm']);
    $blocker->blocks()->create(['blocked_user_id' => $author->id]);
    $muter->mutes()->create(['muted_user_id' => $author->id]);

    publish($author, ['body' => 'Hallo @blockt und @stumm']);

    expect($blocker->fresh()->notifications)->toBeEmpty()->and($muter->fresh()->notifications)->toBeEmpty();
});

it('hides older hints in the inbox once a blockade exists', function () {
    $author = N::member(['display_name' => 'Spätere Blockade']);
    $me = N::member(['username' => 'ich_selbst']);
    publish($author, ['body' => 'Hallo @ich_selbst']);
    $this->actingAs($me)->get(route('notifications.index'))->assertSee('Spätere Blockade');

    $me->blocks()->create(['blocked_user_id' => $author->id]);
    $me->clearSafetyCache();
    $this->actingAs($me->fresh())->get(route('notifications.index'))->assertOk()->assertDontSee('Spätere Blockade');
});

it('does not leak the text of Tropfen the recipient may no longer read', function () {
    $author = N::member();
    $me = N::member(['username' => 'leserin']);
    $post = publish($author, ['body' => 'Geheimer Satz für @leserin']);
    $post->forceFill(['removed_from_oasis_at' => now()])->save();

    $this->actingAs($me)->get(route('notifications.index'))->assertOk()->assertDontSee('Geheimer Satz');
});

it('respects switched-off hint kinds', function () {
    $me = N::member();
    $this->actingAs($me)->get(route('notifications.settings'))->assertOk();
    Livewire::test(Settings::class)
        ->set('kinds', ['follow' => false, 'reply' => true, 'carry' => true, 'mention' => true, 'invitation' => true, 'host' => true])
        ->set('digest', false)->call('save')->assertHasNoErrors();

    app(ManageFollows::class)->follow(N::member(), $me);
    expect($me->fresh()->notifications)->toBeEmpty();
});

it('lets nobody mark someone else\'s hint as read', function () {
    $me = N::member(['username' => 'besitzerin']);
    publish(N::member(), ['body' => 'Hey @besitzerin']);
    $hint = $me->fresh()->notifications->sole();

    $this->actingAs(N::member());
    Livewire::test(Inbox::class)->call('markRead', $hint->id)->assertStatus(404);
    expect($hint->fresh()->read_at)->toBeNull();
    $this->actingAs($me);
    Livewire::test(Inbox::class)->call('markRead', $hint->id)->assertHasNoErrors();
    expect($hint->fresh()->read_at)->not->toBeNull();
});

it('escapes HTML in Tropfen while linking existing @names and #topics', function () {
    N::member(['username' => 'mira']);
    $post = N::post(N::member(), ['body' => '<script>alert(1)</script> Grüße an @mira, @unbekannt und #Garten']);

    $html = $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('href="'.route('oasis.profil', 'mira').'"')
        ->toContain('href="'.route('search.tags.show', 'Garten').'"')
        ->not->toContain('href="'.route('oasis.profil', 'unbekannt').'"');
});
