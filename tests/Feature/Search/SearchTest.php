<?php

use App\Livewire\Search\Index;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

/* Stage 8 search: people, oases and Tropfen for members, public #Thema pages, visibility and blockades. */
beforeEach(fn () => $this->withoutVite());

it('asks for a term and explains how results are ordered', function () {
    $this->actingAs(N::member())->get(route('search.index'))->assertOk()
        ->assertSee('Wonach suchst du?')->assertSee('keine, die jemand bezahlt hat');
    $this->get(route('search.index', ['q' => 'a']))->assertOk()->assertSee('Wonach suchst du?');
});

it('requires a verified member for the search page', function () {
    $this->get(route('search.index'))->assertRedirect('/anmelden');
});

it('finds people by name or handle, exact handle first, only visible and verified ones', function () {
    $me = N::member();
    $exact = N::member(['username' => 'mira', 'display_name' => 'Zora Mira']);
    $partial = N::member(['username' => 'mirabelle', 'display_name' => 'Anna Mirabelle']);
    User::factory()->unverified()->create(['username' => 'mira_unverified']);

    $people = Livewire::actingAs($me)->withQueryParams(['q' => '@Mira'])->test(Index::class)->viewData('people');

    expect($people->pluck('username')->all())->toBe(['mira', 'mirabelle']);
});

it('finds oases and Tropfen, newest first, with LIKE wildcards taken literally', function () {
    $me = N::member();
    $author = N::member();
    N::oasis($author, ['name' => 'Stadtgarten', 'description' => 'Gärtnern in der Stadt']);
    N::post($author, ['body' => 'Alter Tropfen über Tomaten']);
    N::post($author, ['body' => 'Neuer Tropfen über Tomaten']);
    N::post($author, ['body' => '100% Kompost']);
    N::post($author, ['body' => 'Nichts davon']);

    $this->actingAs($me)->get(route('search.index', ['q' => 'tomaten']))->assertOk()
        ->assertSeeInOrder(['Neuer Tropfen über Tomaten', 'Alter Tropfen über Tomaten'])->assertDontSee('Nichts davon');
    expect(Livewire::withQueryParams(['q' => '0%'])->test(Index::class)->viewData('posts')->pluck('body')->all())->toBe(['100% Kompost'])
        ->and(Livewire::withQueryParams(['q' => '%'])->test(Index::class)->viewData('posts'))->toBeNull()
        ->and(Livewire::withQueryParams(['q' => 'garten'])->test(Index::class)->viewData('oases')->pluck('name')->all())->toBe(['Stadtgarten']);
});

it('never shows Tropfen, profiles or oases the member may not read', function () {
    $me = N::member();
    $author = N::member();
    $author->privacySetting()->create(['profile_audience' => 'members']);
    N::post($author, ['body' => 'Versteckt geheim', 'removed_from_oasis_at' => now()]);
    N::oasis($author, ['name' => 'Geheimkreis', 'visibility' => 'members']);

    $response = Livewire::actingAs($me)->withQueryParams(['q' => 'geheim'])->test(Index::class)->assertOk();
    expect($response->viewData('posts')->items())->toBe([])
        ->and($response->viewData('oases')->pluck('name')->all())->toBe(['Geheimkreis']); // members may read members-only oases
});

it('redirects a single #Thema to the topic page', function () {
    $this->actingAs(N::member())->get(route('search.index', ['q' => '#Garten']))
        ->assertRedirect(route('search.tags.show', 'garten'));
});

it('lists Tropfen of a #Thema as whole words, case-insensitive, also for guests', function () {
    $author = N::member();
    N::post($author, ['body' => 'Heute im Beet #Garten #Herbst']);
    N::post($author, ['body' => 'Zaun gestrichen #gartenzaun']);
    N::post($author, ['body' => 'Nur für Mitglieder #garten', 'visibility' => 'members']);
    N::post($author, ['body' => 'Größe zählt #Grünkohl']);

    $this->get(route('search.tags.show', 'garten'))->assertOk()
        ->assertSee('#garten')->assertSee('Heute im Beet')->assertDontSee('Zaun gestrichen')->assertDontSee('Nur für Mitglieder')
        ->assertSee('Du liest als Gast.');
    $this->actingAs(N::member())->get(route('search.tags.show', 'GARTEN'))->assertSee('Nur für Mitglieder');
    $this->get(route('search.tags.show', 'grünkohl'))->assertSee('Größe zählt');
    $this->get(route('search.tags.show', 'a'))->assertNotFound();
    $this->get(route('search.tags.show', 'ab-c'))->assertNotFound();
});

it('hides blocked people and their Tropfen from search results in both directions', function () {
    $me = N::member();
    $blocked = N::member(['username' => 'blockiert', 'display_name' => 'Blockierte Person']);
    N::post($blocked, ['body' => 'Blockierter Tropfen über Tomaten #tomaten']);
    $me->blocks()->create(['blocked_user_id' => $blocked->id]);

    $this->actingAs($me)->get(route('search.index', ['q' => 'blockiert']))->assertOk()
        ->assertDontSee('Blockierte Person');
    $this->get(route('search.index', ['q' => 'tomaten']))->assertDontSee('Blockierter Tropfen');
    $this->get(route('search.tags.show', 'tomaten'))->assertDontSee('Blockierter Tropfen');
    $this->actingAs($blocked)->get(route('search.index', ['q' => $me->username]))->assertDontSee('@'.$me->username.'<', false);
});

it('offers the search in the app navigation', function () {
    $this->actingAs(N::member())->get(route('app.home'))->assertSee('role="search"', false)->assertSee(route('search.index'));
});
