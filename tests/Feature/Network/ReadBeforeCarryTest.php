<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/* Read-before-carry validation and safe external links, including overlapping rich-text tokens. */
beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

it('requires reading confirmation when carrying a linked Tropfen', function ($confirmation) {
    $original = N::post(N::member(), ['body' => 'Lies https://wissen.tld/artikel']);
    $this->actingAs(N::member());

    NetworkActions::publish([
        'body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id,
        'link_read' => $confirmation,
    ])->assertHasErrors(['link_read' => 'accepted']);

    $this->assertDatabaseMissing('posts', ['carried_post_id' => $original->id]);
})->with([null, '0', 'false']);

it('carries linked Tropfen after confirmation', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/artikel']);
    $reader = N::member();

    NetworkActions::publish([
        'body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id, 'link_read' => '1',
    ], $reader)->assertRedirect(route('app.home'))->assertHasNoErrors();

    $this->assertDatabaseHas('posts', ['user_id' => $reader->id, 'carried_post_id' => $original->id, 'body' => 'Meine Gedanken']);
});

it('does not require confirmation for carries without links or for new posts and replies', function ($context) {
    $original = N::post(N::member(), ['body' => $context === 'carried' ? 'Ohne Link' : 'https://wissen.tld/artikel']);
    $reader = N::member();
    $input = ['body' => 'https://eigene.tld/gedanken', 'visibility' => 'public'];
    if ($context !== 'new') {
        $input[$context] = $original->id;
    }

    NetworkActions::publish($input, $reader)->assertHasNoErrors()->assertHasNoErrors();

    $this->assertDatabaseHas('posts', ['user_id' => $reader->id, 'body' => $input['body']]);
})->with(['carried', 'reply_to', 'new']);

it('shows an accessible confirmation and preserves input and validation errors in the reactive carry form', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/eins und http://andere.tld/zwei']);
    $reader = N::member();
    $this->actingAs($reader)->get(route('network.posts.carry', $original))->assertOk()
        ->assertSee('Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.')
        ->assertSee('Ich habe den Inhalt gelesen')->assertSee('href="https://wissen.tld/eins"', false)
        ->assertSee('href="http://andere.tld/zwei"', false)->assertSee('wire:model="link_read"', false);
    $component = NetworkActions::publish(['body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id]);
    $component->assertHasErrors(['link_read' => 'accepted'])->assertSet('body', 'Meine Gedanken')
        ->assertSee('Bestätige bitte, dass du den verlinkten Inhalt gelesen hast.');
    Http::assertNothingSent();
});

it('keeps a checked confirmation after another field fails validation', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/artikel']);
    NetworkActions::publish(['body' => '', 'visibility' => 'public', 'carried' => $original->id, 'link_read' => true], N::member())
        ->assertHasErrors('body')->assertSet('link_read', true);
    $this->assertDatabaseMissing('posts', ['carried_post_id' => $original->id]);
});

it('omits the prompt for a Tropfen without http links', function () {
    $original = N::post(N::member(), ['body' => 'javascript:alert(1) und www.wissen.tld']);

    $this->actingAs(N::member())->get(route('network.posts.carry', $original))
        ->assertOk()->assertDontSee('Ich habe den Inhalt gelesen')->assertDontSee('name="link_read"', false);
});

it('renders external links safely without splitting fragments or mentions inside their paths', function () {
    N::member(['username' => 'alice']);
    $text = 'Grün <script>alert(1)</script> https://wissen.tld/@alice?q=1&b=2#Thema. @alice #Thema';

    $html = Blade::render('<x-network.rich-text :text="$text" />', compact('text'));

    expect($html)->toContain('href="https://wissen.tld/@alice?q=1&amp;b=2#Thema"', 'rel="noopener noreferrer nofollow"',
        'target="_blank"', '>wissen.tld</a>.', '&lt;script&gt;', route('oasis.profil', 'alice'), route('search.tags.show', 'Thema'))
        ->not->toContain('<script>');
    expect(substr_count($html, '<a '))->toBe(3);
    Http::assertNothingSent();
});

it('leaves dangerous schemes credentials and malformed URLs as escaped text', function ($text) {
    $html = Blade::render('<x-network.rich-text :text="$text" />', compact('text'));

    expect($html)->not->toContain('<a ', '<img ', '<script');
})->with([
    'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'ftp://wissen.tld/a',
    '//wissen.tld/a', 'https://', 'https://wissen.tld@evil.tld/a', 'https://wissen.tld\\evil.tld/a',
    '<img src=x onerror=alert(1)>',
]);

it('keeps balanced parentheses in links but leaves surrounding punctuation outside', function () {
    $text = '(https://wissen.tld/a_(b)), HTTP://andere.tld/seite!';

    $html = Blade::render('<x-network.rich-text :text="$text" />', compact('text'));

    expect($html)->toContain('href="https://wissen.tld/a_(b)"', '>wissen.tld</a>),', 'href="HTTP://andere.tld/seite"', '>andere.tld</a>!');
});
