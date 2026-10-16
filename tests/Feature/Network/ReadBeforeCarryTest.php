<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Tests\Support\NetworkFixtures as N;

/* Read-before-carry validation and safe external links, including overlapping rich-text tokens. */
beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

it('requires reading confirmation when carrying a linked Tropfen', function ($confirmation) {
    $original = N::post(N::member(), ['body' => 'Lies https://wissen.tld/artikel']);
    $this->actingAs(N::member());

    $this->post(route('network.posts.store'), [
        'body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id,
        'link_read' => $confirmation,
    ])->assertSessionHasErrors(['link_read' => 'Bestätige bitte, dass du den verlinkten Inhalt gelesen hast.']);

    $this->assertDatabaseMissing('posts', ['carried_post_id' => $original->id]);
})->with([null, '0', 'false']);

it('carries linked Tropfen after confirmation', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/artikel']);
    $reader = N::member();

    $this->actingAs($reader)->post(route('network.posts.store'), [
        'body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id, 'link_read' => '1',
    ])->assertRedirect(route('app.home'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('posts', ['user_id' => $reader->id, 'carried_post_id' => $original->id, 'body' => 'Meine Gedanken']);
});

it('does not require confirmation for carries without links or for new posts and replies', function ($context) {
    $original = N::post(N::member(), ['body' => $context === 'carried' ? 'Ohne Link' : 'https://wissen.tld/artikel']);
    $reader = N::member();
    $input = ['body' => 'https://eigene.tld/gedanken', 'visibility' => 'public'];
    if ($context !== 'new') {
        $input[$context] = $original->id;
    }

    $this->actingAs($reader)->post(route('network.posts.store'), $input)->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('posts', ['user_id' => $reader->id, 'body' => $input['body']]);
})->with(['carried', 'reply_to', 'new']);

it('shows a required accessible confirmation associated with the carry form and preserves errors', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/eins und http://andere.tld/zwei']);
    $url = route('network.posts.carry', $original);
    $this->actingAs(N::member());

    $this->get($url)->assertOk()
        ->assertSee('Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.')
        ->assertSee('Ich habe den Inhalt gelesen')
        ->assertSee('href="https://wissen.tld/eins"', false)
        ->assertSee('href="http://andere.tld/zwei"', false)
        ->assertSee('form="carry-form"', false)->assertSee('id="carry-form"', false);

    $dom = new DOMDocument;
    @$dom->loadHTML($this->get($url)->getContent());
    $checkbox = (new DOMXPath($dom))->query('//input[@name="link_read"]')->item(0);
    expect($checkbox->hasAttribute('required'))->toBeTrue();
    expect($checkbox->getAttribute('form'))->toBe('carry-form');

    $this->from($url)->post(route('network.posts.store'), [
        'body' => 'Meine Gedanken', 'visibility' => 'public', 'carried' => $original->id,
    ])->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee('Bestätige bitte, dass du den verlinkten Inhalt gelesen hast.')
        ->assertSee('aria-invalid="true"', false)->assertSee('Meine Gedanken');
    Http::assertNothingSent();
});

it('keeps a checked confirmation after another field fails validation', function () {
    $original = N::post(N::member(), ['body' => 'https://wissen.tld/artikel']);
    $url = route('network.posts.carry', $original);
    $this->actingAs(N::member())->from($url)->post(route('network.posts.store'), [
        'body' => '', 'visibility' => 'public', 'carried' => $original->id, 'link_read' => '1',
    ])->assertSessionHasErrors('body');

    $dom = new DOMDocument;
    @$dom->loadHTML($this->get($url)->assertOk()->getContent());
    $checkbox = (new DOMXPath($dom))->query('//input[@name="link_read"]')->item(0);

    expect($checkbox->hasAttribute('checked'))->toBeTrue();
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
