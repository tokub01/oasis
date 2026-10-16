<?php

use App\Livewire\Search\Index;
use App\Livewire\Search\Tag;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

test('live search reacts to URL-bound input without full navigation', function () {
    $author = N::member();
    N::post($author, ['body' => 'Ein Garten voller Tomaten']);
    Livewire::actingAs(N::member())->test(Index::class)->set('q', 'Tomaten')->assertSee('Ein Garten voller Tomaten')
        ->set('q', 'Fehlanzeige')->assertDontSee('Ein Garten voller Tomaten');
});

test('live search validates input and retains the search budget on refresh and actions', function (string $method) {
    $viewer = N::member();
    $component = Livewire::actingAs($viewer)->test(Index::class);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        RateLimiter::hit('search:'.$viewer->id, 60);
    }
    $component->call($method)->assertHasErrors('q')->assertSee('Warte bitte eine Minute.');
    RateLimiter::clear('search:'.$viewer->id);
    $component->call($method)->assertHasNoErrors('q');
})->with(['search', '$refresh']);

test('topic pagination cannot bypass guest search limits or change the locked topic', function () {
    $component = Livewire::test(Tag::class, ['tag' => 'garten']);
    expect(fn () => $component->set('tag', 'privat'))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('hashtag input uses SPA navigation to the same topic route', function () {
    Livewire::actingAs(N::member())->test(Index::class)->set('q', '#Garten')->assertRedirect(route('search.tags.show', 'garten'));
});

test('topic rate limits remain inline and suppress further feed queries', function () {
    $viewer = N::member();
    N::post($viewer, ['body' => 'Ein Tropfen ueber #garten']);
    $component = Livewire::actingAs($viewer)->test(Tag::class, ['tag' => 'garten']);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        RateLimiter::hit('search:'.$viewer->id, 60);
    }

    $component->call('$refresh')->assertOk()->assertHasErrors('tag')->assertSee('Warte bitte eine Minute.')
        ->assertDontSee('Ein Tropfen ueber');
});

test('overlong URL search terms show the same inline validation as live input', function () {
    $this->withoutVite();
    $this->actingAs(N::member())->get(route('search.index', ['q' => str_repeat('a', 101)]))
        ->assertOk()->assertSee('Dein Suchbegriff darf höchstens 100 Zeichen lang sein.');
});
