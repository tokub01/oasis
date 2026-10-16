<?php

use App\Actions\Network\ManageFollows;
use App\Ai\Agents\ContentCheck;
use App\Livewire\Network\Compose;
use App\Livewire\Network\Discover;
use App\Livewire\Network\Invitations;
use App\Livewire\Network\OasisClose;
use App\Livewire\Network\OasisForm;
use App\Livewire\Network\OasisIndex;
use App\Livewire\Network\OasisShow;
use App\Livewire\Network\PostShow;
use App\Livewire\Network\ProfileEdit;
use App\Livewire\Network\Statistics;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

test('network pages are routed to class-based full-page Livewire components', function () {
    $user = N::member();
    $oasis = N::oasis($user);
    $post = N::post($user);
    foreach (['network.discover' => Discover::class,
        'network.oases.index' => OasisIndex::class,
        'network.profile.edit' => ProfileEdit::class,
        'network.stats' => Statistics::class,
        'network.invitations.index' => Invitations::class] as $route => $component) {
        $this->actingAs($user)->get(route($route))->assertOk()->assertSeeLivewire($component);
    }
    $this->get(route('network.posts.show', $post))->assertOk()->assertSeeLivewire(PostShow::class);
    $this->get(route('network.oases.show', $oasis))->assertOk()->assertSeeLivewire(OasisShow::class);
});

test('the composer locks every server-selected posting context', function (string $property) {
    $author = N::member();
    $post = N::post($author);
    $component = Livewire::actingAs($author)->test(Compose::class);
    expect(fn () => $component->set($property, $post->id))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['oasisId', 'replyToId', 'carriedId']);

test('losing participation after mounting prevents both tone previews and publishing', function () {
    $author = N::member();
    $component = Livewire::actingAs($author)->test(Compose::class)->set('body', 'Ein ruhiger Tropfen');
    $author->update(['trial_ends_at' => now()->subDay()]);
    $component->call('publish')->assertForbidden();
    expect(Post::count())->toBe(0);
});

test('oasis hosts are reauthorized before saves role changes and closing', function (string $operation) {
    $host = N::member();
    $other = N::member();
    $oasis = N::oasis($host);
    app(ManageFollows::class)->follow($other, $oasis);
    $component = Livewire::actingAs($host)->test($operation === 'close' ? OasisClose::class : OasisForm::class, ['oasis' => $oasis]);
    $oasis->members()->updateExistingPivot($host->id, ['role' => 'member']);
    if ($operation === 'setRole') {
        $component->call('setRole', $other->id, 'host')->assertForbidden();
    } else {
        $component->call($operation)->assertForbidden();
    }
    expect($oasis->fresh())->not->toBeNull();
    expect($oasis->isHost($other))->toBeFalse();
})->with(['save', 'setRole', 'close']);

test('post visibility changes are applied during a live refresh', function () {
    $author = N::member();
    $post = N::post($author);
    $component = Livewire::test(PostShow::class, ['post' => $post])->assertSee($post->body);
    $post->update(['visibility' => 'members']);
    $component->call('$refresh')->assertNotFound();
});

test('publishing keeps the ten-per-minute route budget on Livewire actions', function () {
    $author = N::member();
    $component = Livewire::actingAs($author)->test(Compose::class)->set('body', 'Ein Tropfen');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        RateLimiter::hit('network-posts:'.$author->id, 60);
    }
    $component->call('publish')->assertHasErrors('body');
    expect(Post::count())->toBe(0);
});

test('tone hints update while typing and require explicit confirmation on send', function () {
    config(['oasis.moderation.enabled' => false]);
    $author = N::member();
    $component = Livewire::actingAs($author)->test(Compose::class)->set('body', 'Du Arschloch');
    $component->assertSet('toneNudge', true)->assertSee('Nochmal lesen?');
    $component->call('publish')->assertHasErrors('body');
    expect(Post::count())->toBe(0);
    $component->set('tone_confirmed', true)->call('publish')->assertDispatched('post-published');
    expect(Post::sole()->user_id)->toBe($author->id);
});

test('blocking a post author leaves the now unreadable detail page with SPA navigation', function () {
    $viewer = N::member();
    $author = N::member();
    $post = N::post($author);
    Livewire::actingAs($viewer)->test(PostShow::class, ['post' => $post])
        ->call('blockUser', $author->id)->assertRedirect(route('app.home'));
    expect($viewer->fresh()->isBlockedWith($author))->toBeTrue();
});

test('removing a foreign oasis post leaves its now unreadable detail page', function () {
    $host = N::member();
    $oasis = N::oasis($host);
    $post = N::post(N::member(), ['oasis_id' => $oasis->id]);
    Livewire::actingAs($host)->test(PostShow::class, ['post' => $post])
        ->call('removePostFromOasis', $post->id)->assertRedirect(route('app.home'));
    expect($post->fresh()->removed_from_oasis_at)->not->toBeNull();
});

test('a host handing their role to an existing cohost leaves the host-only form', function () {
    $host = N::member();
    $cohost = N::member();
    $oasis = N::oasis($host);
    app(ManageFollows::class)->follow($cohost, $oasis);
    $oasis->members()->updateExistingPivot($cohost->id, ['role' => 'host']);
    Livewire::actingAs($host)->test(OasisForm::class, ['oasis' => $oasis])
        ->call('setRole', $host->id, 'member')->assertRedirect(route('network.oases.show', $oasis));
    expect($oasis->isHost($host))->toBeFalse()->and($oasis->isHost($cohost))->toBeTrue();
});

test('tone previews reauthorize when participation expires after mounting', function () {
    $author = N::member();
    $component = Livewire::actingAs($author)->test(Compose::class);
    $author->update(['trial_ends_at' => now()->subDay()]);
    $component->set('body', 'Ein neuer Text')->assertForbidden();
});

test('oasis editing never serializes member emails into public component properties', function () {
    $host = N::member();
    $member = N::member();
    $oasis = N::oasis($host);
    app(ManageFollows::class)->follow($member, $oasis);
    $component = Livewire::actingAs($host)->test(OasisForm::class, ['oasis' => $oasis]);
    expect(json_encode($component->getData()))->not->toContain($member->email);
});

test('draft tone hints use only the local word list and never prompt the language model', function () {
    config(['oasis.moderation.enabled' => true]);
    ContentCheck::fake();

    Livewire::actingAs(N::member())->test(Compose::class)->set('body', 'Du Arschloch')
        ->assertSet('toneNudge', true)->assertSee('Nochmal lesen?')
        ->set('body', 'Ein ruhiger unveroeffentlichter Entwurf')->assertSet('toneNudge', false);

    ContentCheck::assertNeverPrompted();
    expect(Post::count())->toBe(0);
});

test('selected images are validated immediately before the post is submitted', function () {
    $component = Livewire::actingAs(N::member())->test(Compose::class);
    $component->set('images', [UploadedFile::fake()->create('rechnung.pdf', 10, 'application/pdf')])
        ->assertHasErrors('images.0')->call('removeImage', 0)->assertHasNoErrors();
    $component->set('images', [UploadedFile::fake()->image('zu-gross.jpg')->size(8193)])
        ->assertHasErrors('images.0')->call('removeImage', 0)->assertHasNoErrors();
    $component->set('images', [UploadedFile::fake()->image('foto.jpg')])
        ->assertHasNoErrors('images.0');
    expect(Post::count())->toBe(0);
});

test('empty profile text stays nullable when saved through Livewire', function () {
    $user = N::member();
    Livewire::actingAs($user)->test(ProfileEdit::class)->set('bio', '')->set('location', '')
        ->call('save')->assertHasNoErrors();
    expect($user->profile()->sole()->bio)->toBeNull();
    expect($user->profile()->sole()->location)->toBeNull();
});

test('removing an uploaded image preserves the other image description and draft text', function () {
    $component = Livewire::actingAs(N::member())->test(Compose::class)->set('body', 'Mein Entwurf')
        ->set('images', [UploadedFile::fake()->image('eins.jpg'), UploadedFile::fake()->image('zwei.jpg')])
        ->set('alts', ['Erstes Motiv', 'Zweites Motiv']);

    $component->call('removeImage', 0)->assertHasNoErrors()->assertSet('alts', ['Zweites Motiv'])->assertSet('body', 'Mein Entwurf');
    expect($component->get('images'))->toHaveCount(1);
    expect($component->get('images')[0]->getClientOriginalName())->toBe('zwei.jpg');
});

test('upload actions reauthorize after the account loses participation', function (string $method, array $arguments) {
    $author = N::member();
    $component = Livewire::actingAs($author)->test(Compose::class);
    $author->update(['trial_ends_at' => now()->subDay()]);

    if ($method === 'updatedImages') {
        $component->set('images', [])->assertForbidden();
    } else {
        $component->call($method, ...$arguments)->assertForbidden();
    }
})->with([['updatedImages', []], ['removeImage', [0]]]);
