<?php

use App\Actions\Safety\ManageBlock;
use App\Actions\Safety\ManageMute;
use App\Livewire\Search\Index;
use App\Livewire\Search\Tag;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

/*
 * --------------------------------------------------------------------------
 * 1. Authentication, Guests & Rate-Limiting
 * --------------------------------------------------------------------------
 */

it('requires authentication and verified email for member search (/app/suche)', function (): void {
    $this->get(route('search.index'))
        ->assertRedirect('/anmelden');

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)
        ->get(route('search.index'))
        ->assertRedirect(route('verification.notice'));
});

it('allows guests to view public topic pages without authentication', function (): void {
    $author = N::member();
    N::post($author, ['body' => 'Ein öffentlicher Beitrag zu #wetter']);

    $this->get(route('search.tags.show', 'wetter'))
        ->assertOk()
        ->assertSee('#wetter')
        ->assertSee('Ein öffentlicher Beitrag zu')
        ->assertSee('Du liest als Gast.');
});

it('enforces throttle:search rate limit of 30 requests per minute', function (): void {
    $me = N::member();
    $limiter = app(RateLimiter::class);
    $limiter->clear('search:'.$me->id);

    // Perform 30 successful requests
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($me)->get(route('search.index', ['q' => 'test']))->assertOk();
    }

    // 31st request should be throttled (429)
    $this->actingAs($me)->get(route('search.index', ['q' => 'test']))
        ->assertStatus(429);

    $limiter->clear('search:'.$me->id);
});

/*
 * --------------------------------------------------------------------------
 * 2. Search Term Lengths & Validation
 * --------------------------------------------------------------------------
 */

it('renders the initial guidance page without queries when term is empty or shorter than 2 characters', function (): void {
    $me = N::member();

    $this->actingAs($me)->get(route('search.index'))
        ->assertOk()
        ->assertSee('Wonach suchst du?')
        ->assertSee('So sucht Oasis');

    $this->actingAs($me)->get(route('search.index', ['q' => '']))
        ->assertOk()
        ->assertSee('Wonach suchst du?');

    $this->actingAs($me)->get(route('search.index', ['q' => 'a']))
        ->assertOk()
        ->assertSee('Wonach suchst du?');

    $this->actingAs($me)->get(route('search.index', ['q' => '   ']))
        ->assertOk()
        ->assertSee('Wonach suchst du?');
});

it('rejects search terms longer than 100 characters with a validation error', function (): void {
    $me = N::member();
    $tooLong = str_repeat('a', 101);

    Livewire::actingAs($me)->test(Index::class)->set('q', $tooLong)->assertHasErrors('q');

    $this->actingAs($me)->get(route('search.index', ['q' => $tooLong]))
        ->assertOk()->assertSee('Dein Suchbegriff darf höchstens 100 Zeichen lang sein.');
});

it('accepts search terms with exactly 100 characters', function (): void {
    $me = N::member();
    $maxTerm = str_repeat('a', 100);

    $this->actingAs($me)->get(route('search.index', ['q' => $maxTerm]))
        ->assertOk();
});

/*
 * --------------------------------------------------------------------------
 * 3. #Thema Redirects & Tag Validation
 * --------------------------------------------------------------------------
 */

it('redirects an exact single #Thema to the topic page preserving lowercase', function (): void {
    $me = N::member();

    $this->actingAs($me)->get(route('search.index', ['q' => '#Garten']))
        ->assertRedirect(route('search.tags.show', 'garten'));

    $this->actingAs($me)->get(route('search.index', ['q' => '#GÄRTNERN']))
        ->assertRedirect(route('search.tags.show', 'gärtnern'));

    $this->actingAs($me)->get(route('search.index', ['q' => '#stadt_natur']))
        ->assertRedirect(route('search.tags.show', 'stadt_natur'));

    $this->actingAs($me)->get(route('search.index', ['q' => '  #frühling  ']))
        ->assertRedirect(route('search.tags.show', 'frühling'));
});

it('does not redirect if the term contains more than a single #Thema', function (): void {
    $me = N::member();

    Livewire::withQueryParams(['q' => '#garten und mehr'])->actingAs($me)->test(Index::class)
        ->assertOk()
        ->assertSee('wire:name="search"', false);

    Livewire::withQueryParams(['q' => 'hallo #garten'])->actingAs($me)->test(Index::class)
        ->assertOk()
        ->assertSee('wire:name="search"', false);

    Livewire::withQueryParams(['q' => '#garten #bäume'])->actingAs($me)->test(Index::class)
        ->assertOk()
        ->assertSee('wire:name="search"', false);
});

it('aborts with 404 for invalid topic tags on /themen/{tag}', function (string $invalidTag): void {
    $this->get('/themen/'.urlencode($invalidTag))
        ->assertNotFound();
})->with([
    'single letter' => 'a',
    'contains hyphen' => 'garten-zaun',
    'contains exclamation' => 'garten!',
    'contains dot' => 'garten.de',
    'contains slash' => 'garten/zaun',
    'contains space' => 'garten zaun',
    'longer than 40 characters' => str_repeat('a', 41),
]);

/*
 * --------------------------------------------------------------------------
 * 4. SQL / LIKE Wildcards & Injection Prevention
 * --------------------------------------------------------------------------
 */

it('escapes percent wildcard so that % is matched literally', function (): void {
    $me = N::member();
    $author = N::member();
    N::post($author, ['body' => 'Ersparnis von 50% beim Einkauf']);
    N::post($author, ['body' => 'Ersparnis von 500 Euro beim Einkauf']);

    $response = Livewire::withQueryParams(['q' => '50%'])->actingAs($me)->test(Index::class)->assertOk();
    $posts = $response->viewData('posts');

    expect($posts)->toHaveCount(1)
        ->and($posts->first()->body)->toBe('Ersparnis von 50% beim Einkauf');
});

it('escapes underscore wildcard so that _ is matched literally', function (): void {
    $me = N::member();
    $author = N::member();
    N::post($author, ['body' => 'Code Variable my_var definiert']);
    N::post($author, ['body' => 'Code Variable myxvar definiert']);

    $response = Livewire::withQueryParams(['q' => 'my_var'])->actingAs($me)->test(Index::class)->assertOk();
    $posts = $response->viewData('posts');

    expect($posts)->toHaveCount(1)
        ->and($posts->first()->body)->toBe('Code Variable my_var definiert');
});

it('escapes backslash so that backslashes are matched literally', function (): void {
    $me = N::member();
    $author = N::member();
    N::post($author, ['body' => 'Pfad C:\Oasis\AppData']);
    N::post($author, ['body' => 'Pfad C:OasisAppData']);

    $response = Livewire::withQueryParams(['q' => 'C:\Oasis'])->actingAs($me)->test(Index::class)->assertOk();
    $posts = $response->viewData('posts');

    expect($posts)->toHaveCount(1)
        ->and($posts->first()->body)->toBe('Pfad C:\Oasis\AppData');
});

it('safely handles quotation marks and potential SQL injection characters', function (string $term): void {
    $me = N::member();
    $author = N::member();
    N::post($author, ['body' => "Garten von O'Reilly & Söhne"]);
    N::post($author, ['body' => 'Tomaten der Sorte "Roma" im Topf']);

    Livewire::withQueryParams(['q' => $term])->actingAs($me)->test(Index::class)
        ->assertOk();
})->with([
    'single quote' => "O'Reilly",
    'double quotes' => '"Roma"',
    'sql injection attempt 1' => "' OR '1'='1",
    'sql injection attempt 2' => "'; DROP TABLE posts; --",
    'multiple percent wildcards' => '%%%',
    'multiple underscores' => '____',
]);

/*
 * --------------------------------------------------------------------------
 * 5. REGEXP Matching for #Themen (Whole Word, Bounds, Unicode)
 * --------------------------------------------------------------------------
 */

it('matches #Thema as whole word and does not match prefixes or suffixes', function (): void {
    $author = N::member();
    N::post($author, ['body' => 'Alles über #garten im Frühling']);
    N::post($author, ['body' => 'Ein schöner #gartenzaun aus Holz']);
    N::post($author, ['body' => 'Der #hausgarten blüht schon']);

    $response = Livewire::test(Tag::class, ['tag' => 'garten'])->assertOk();
    $posts = $response->viewData('posts');

    expect($posts)->toHaveCount(1)
        ->and($posts->first()->body)->toBe('Alles über #garten im Frühling');
});

it('matches #Thema at boundaries such as beginning, end and next to punctuation', function (): void {
    $author = N::member();
    $p1 = N::post($author, ['body' => '#garten am Anfang des Textes']);
    $p2 = N::post($author, ['body' => 'Am Ende steht #garten']);
    $p3 = N::post($author, ['body' => 'In Klammern (#garten) gesetzt']);
    $p4 = N::post($author, ['body' => 'Mit Satzzeichen: #garten!']);
    $p5 = N::post($author, ['body' => 'Mit Komma danach: #garten, herrlich.']);
    $p6 = N::post($author, ['body' => 'Doppelpunkt davor: Thema:#garten']);

    $response = Livewire::test(Tag::class, ['tag' => 'garten'])->assertOk();
    $ids = collect($response->viewData('posts')->items())->pluck('id')->all();

    expect($ids)->toContain($p1->id, $p2->id, $p3->id, $p4->id, $p5->id, $p6->id);
});

it('does not match #Thema when embedded inside a URL or email-like string', function (): void {
    $author = N::member();
    N::post($author, ['body' => 'Besuche https://oasis.test/themen#garten']);
    N::post($author, ['body' => 'Schreib an info#garten@example.com']);

    $response = Livewire::test(Tag::class, ['tag' => 'garten'])->assertOk();
    expect($response->viewData('posts')->items())->toBeEmpty();
});

it('matches #Thema case-insensitively with German umlauts', function (): void {
    $author = N::member();
    N::post($author, ['body' => 'Erntezeit für frischen #Grünkohl']);
    N::post($author, ['body' => 'Alles über #ÜBER_UNS im Verein']);

    $res1 = Livewire::test(Tag::class, ['tag' => 'grünkohl'])->assertOk();
    expect($res1->viewData('posts'))->toHaveCount(1);

    $res2 = Livewire::test(Tag::class, ['tag' => 'GRÜNKOHL'])->assertOk();
    expect($res2->viewData('posts'))->toHaveCount(1);

    $res3 = Livewire::test(Tag::class, ['tag' => 'über_uns'])->assertOk();
    expect($res3->viewData('posts'))->toHaveCount(1);
});

/*
 * --------------------------------------------------------------------------
 * 6. Content Visibility: Guests vs Members vs Oasis-Moderation
 * --------------------------------------------------------------------------
 */

it('restricts guests to public posts on topic pages, hiding members-only posts', function (): void {
    $author = N::member();
    $publicPost = N::post($author, ['body' => 'Öffentlicher Post zu #reisen', 'visibility' => 'public']);
    $membersPost = N::post($author, ['body' => 'Interner Post zu #reisen', 'visibility' => 'members']);

    // Guest view
    $guestResponse = Livewire::test(Tag::class, ['tag' => 'reisen'])->assertOk();
    $guestIds = collect($guestResponse->viewData('posts')->items())->pluck('id')->all();
    expect($guestIds)->toBe([$publicPost->id])
        ->and($guestIds)->not->toContain($membersPost->id);

    // Member view
    $member = N::member();
    $memberResponse = Livewire::actingAs($member)->test(Tag::class, ['tag' => 'reisen'])->assertOk();
    $memberIds = collect($memberResponse->viewData('posts')->items())->pluck('id')->all();
    expect($memberIds)->toContain($publicPost->id, $membersPost->id);
});

it('hides posts removed from oasis from search and topic pages for third parties', function (): void {
    $author = N::member();
    $viewer = N::member();

    $removed = N::post($author, [
        'body' => 'Moderierter Tropfen zu #zensur',
        'removed_from_oasis_at' => now(),
    ]);

    // Viewer should not see it in search or on topic page
    Livewire::withQueryParams(['q' => 'moderierter'])->actingAs($viewer)->test(Index::class)
        ->assertOk()
        ->assertDontSee('Moderierter Tropfen');

    Livewire::actingAs($viewer)->test(Tag::class, ['tag' => 'zensur'])
        ->assertOk()
        ->assertDontSee('Moderierter Tropfen');

    // Author still sees own removed post
    Livewire::withQueryParams(['q' => 'moderierter'])->actingAs($author)->test(Index::class)
        ->assertOk()
        ->assertSee('Moderierter Tropfen');
});

/*
 * --------------------------------------------------------------------------
 * 7. Safety: Bidirectional Blocks & Mutes
 * --------------------------------------------------------------------------
 */

it('suppresses blocked accounts and their posts from search in both directions', function (): void {
    $alice = N::member(['username' => 'alice_cross', 'display_name' => 'Alice Cross']);
    $bob = N::member(['username' => 'bob_cross', 'display_name' => 'Bob Cross']);

    N::post($bob, ['body' => 'Bobs geheimer Suchtropfen']);
    N::post($alice, ['body' => 'Alices geheimer Suchtropfen']);

    app(ManageBlock::class)->block($alice, $bob);

    // Alice searches for Bob
    $aliceSearch = Livewire::withQueryParams(['q' => 'bob_cross'])->actingAs($alice)->test(Index::class)->assertOk();
    expect($aliceSearch->viewData('people')->pluck('id')->all())->not->toContain($bob->id);
    expect(Livewire::withQueryParams(['q' => 'Bobs geheimer'])->actingAs($alice)->test(Index::class)->viewData('posts')->items())->toBeEmpty();

    // Bob searches for Alice (reverse direction)
    $bobSearch = Livewire::withQueryParams(['q' => 'alice_cross'])->actingAs($bob)->test(Index::class)->assertOk();
    expect($bobSearch->viewData('people')->pluck('id')->all())->not->toContain($alice->id);
    expect(Livewire::withQueryParams(['q' => 'Alices geheimer'])->actingAs($bob)->test(Index::class)->viewData('posts')->items())->toBeEmpty();
});

it('suppresses muted accounts posts from search results without hiding their profile', function (): void {
    $alice = N::member();
    $bob = N::member(['username' => 'bob_muted', 'display_name' => 'Bob Muted']);

    N::post($bob, ['body' => 'Bobs Tropfen der stumm sein soll']);

    app(ManageMute::class)->mute($alice, $bob);

    // Bob's posts must be hidden from Alice's search results
    $res = Livewire::withQueryParams(['q' => 'stumm sein soll'])->actingAs($alice)->test(Index::class)->assertOk();
    expect($res->viewData('posts')->items())->toBeEmpty();

    // But Bob's profile remains findable in DirectorySearch (unilateral mute leaves profile accessible)
    $peopleRes = Livewire::withQueryParams(['q' => 'bob_muted'])->actingAs($alice)->test(Index::class)->assertOk();
    expect($peopleRes->viewData('people')->pluck('id')->all())->toContain($bob->id);
});

/*
 * --------------------------------------------------------------------------
 * 8. Directory Search Ordering & Boundaries
 * --------------------------------------------------------------------------
 */

it('orders people search with exact username match first, then alphabetical', function (): void {
    $me = N::member();
    $partial = N::member(['username' => 'alex_meier', 'display_name' => 'Berta Meier']);
    $exact = N::member(['username' => 'alex', 'display_name' => 'Zacharias Alex']);

    $people = Livewire::withQueryParams(['q' => '@alex'])->actingAs($me)->test(Index::class)->assertOk()->viewData('people');

    expect($people->pluck('username')->all())->toBe(['alex', 'alex_meier']);
});

it('never includes unverified users in people search', function (): void {
    $me = N::member();
    $unverified = User::factory()->unverified()->create(['username' => 'phantom_user', 'display_name' => 'Phantom']);

    $people = Livewire::withQueryParams(['q' => 'phantom'])->actingAs($me)->test(Index::class)->assertOk()->viewData('people');

    expect($people->pluck('id')->all())->not->toContain($unverified->id);
});

it('limits people and oases search results to maximum 12 items', function (): void {
    $me = N::member();

    // Create 15 matching members
    foreach (range(1, 15) as $i) {
        N::member(['username' => sprintf('bulk_user_%02d', $i), 'display_name' => 'Bulk User '.$i]);
    }

    // Create 15 matching oases
    foreach (range(1, 15) as $i) {
        N::oasis($me, ['name' => sprintf('Bulk Oasis %02d', $i)]);
    }

    $response = Livewire::withQueryParams(['q' => 'bulk'])->actingAs($me)->test(Index::class)->assertOk();

    expect($response->viewData('people'))->toHaveCount(12)
        ->and($response->viewData('oases'))->toHaveCount(12);
});

it('does not return all users when searching for double at-signs (@@)', function (): void {
    $me = N::member();
    $randomUser = N::member(['username' => 'totally_unrelated', 'display_name' => 'Unrelated Person']);

    $response = Livewire::withQueryParams(['q' => '@@'])->actingAs($me)->test(Index::class)->assertOk();
    $people = $response->viewData('people');

    expect($people->pluck('id')->all())->not->toContain($randomUser->id);
});
