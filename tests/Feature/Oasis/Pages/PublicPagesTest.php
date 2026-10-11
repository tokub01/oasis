<?php

/* Independent checks against the public website brief and design copy. */

beforeEach(function () {
    $this->withoutVite();
    config(['oasis.demo_pages' => true, 'oasis.checkout_enabled' => false]);
});

dataset('oasis public pages', [
    'home' => ['home', [], 'Social Media, das dir'],
    'features' => ['funktionen', [], 'Quelle'],
    'prices' => ['preise', [], 'Dieselbe Oase für alle.'],
    'transparency' => ['transparenz', [], 'Jeder Euro, offen gelegt.'],
    'manifesto' => ['manifest', [], 'Ein Ort im Netz, der seinen Mitgliedern gehört.'],
    'help' => ['hilfe', [], 'Wie können wir helfen?'],
    'trial' => ['testen', [], 'Tage Oasis, kostenlos.'],
    'login' => ['anmelden', [], 'Willkommen zurück.'],
    'discover' => ['entdecken', [], 'Beispielansicht'],
    'profile' => ['profil', ['handle' => 'mira'], 'Mira Sol'],
    'groups' => ['gemeinsam', [], 'Gemeinsam'],
    'waitlist' => ['gruendung', [], 'Startliste'],
    'cancellation' => ['kuendigen', [], 'Jetzt kündigen'],
    'withdrawal' => ['widerrufen', [], 'Widerruf bestätigen'],
    'reports' => ['melden', [], 'Inhalte melden'],
    'imprint' => ['impressum', [], 'Impressum'],
    'privacy' => ['datenschutz', [], 'Datenschutz'],
    'terms' => ['agb', [], 'AGB'],
    'withdrawal policy' => ['widerrufsbelehrung', [], 'Widerrufsbelehrung'],
    'community rules' => ['regeln', [], 'Community-Regeln'],
]);

it('renders every public route with a single h1, German landmarks and direct contract links', function ($page, $parameters, $copy) {
    $response = $this->get(route('oasis.'.$page, $parameters))->assertOk()->assertSee($copy);
    $html = $response->getContent();

    expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
    $response->assertSee('<html lang="de">', false)
        ->assertSee('<main id="inhalt"', false)
        ->assertSee('Zum Inhalt springen')
        ->assertSee('Verträge hier kündigen')->assertSee(route('oasis.kuendigen'), false)
        ->assertSee('Vertrag widerrufen')->assertSee(route('oasis.widerrufen'), false);
    expect($html)->not->toContain('fonts.googleapis.com', 'fonts.bunny.net', '.dc.html', '<sc-if', '<sc-for');
})->with('oasis public pages');

it('gives each public page a distinct title and description', function () {
    $titles = [];
    $descriptions = [];
    foreach (['home', 'funktionen', 'preise', 'transparenz', 'manifest', 'hilfe', 'testen', 'anmelden',
        'entdecken', 'gemeinsam', 'gruendung', 'kuendigen', 'widerrufen', 'melden', 'impressum',
        'datenschutz', 'agb', 'widerrufsbelehrung', 'regeln'] as $page) {
        $html = $this->get(route('oasis.'.$page))->assertOk()->getContent();
        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        preg_match('/<meta name="description" content="([^"]+)"/', $html, $description);
        expect($title[1] ?? '')->not->toBe('');
        expect($description[1] ?? '')->not->toBe('');
        $titles[] = $title[1];
        $descriptions[] = $description[1];
    }
    expect(count(array_unique($titles)))->toBe(count($titles));
    expect(count(array_unique($descriptions)))->toBe(count($descriptions));
});

it('renders the designed noindex 404 page for a missing URL', function () {
    $response = $this->get('/missing-oasis-page')->assertNotFound()->assertSee('Fata Morgana.');
    expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    $response->assertSee('content="noindex, nofollow"', false)->assertSee('Verträge hier kündigen');
});

it('redirects the existing named login URL to the public sign-in page', function () {
    $this->get(route('login'))->assertRedirect(route('oasis.anmelden'));
});

it('hides checkout until enabled and renders the exact order label when enabled', function () {
    $this->get(route('oasis.mitglied'))->assertNotFound();
    config(['oasis.checkout_enabled' => true]);
    $response = $this->get(route('oasis.mitglied'))->assertOk()->assertSee('Zahlungspflichtig bestellen');
    expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
});

it('marks sample pages noindex and hides them when the demo flag is disabled', function ($page, $parameters) {
    $this->get(route('oasis.'.$page, $parameters))->assertOk()->assertSee('Beispielansicht')
        ->assertSee('content="noindex, nofollow"', false);
    config(['oasis.demo_pages' => false]);
    $this->get(route('oasis.'.$page, $parameters))->assertNotFound();
})->with([
    'discover' => ['entdecken', []],
    'profile' => ['profil', ['handle' => 'mira']],
]);

it('returns 404 for unknown or invalid profile handles', function () {
    $this->get('/profil/unknown')->assertNotFound();
    $this->get('/profil/invalid-handle')->assertNotFound();
});

it('renders changed trial days consistently in the shared navigation and page copy', function ($page) {
    config(['oasis.trial_days' => 47]);
    $this->get(route('oasis.'.$page))->assertOk()->assertSee('47 Tage')->assertDontSee('30 Tage');
})->with(['home', 'preise', 'funktionen', 'testen', 'anmelden', 'hilfe']);

it('renders changed membership prices and derives yearly and daily amounts from config', function () {
    config([
        'oasis.tiers.solidar.monthly' => 7.25,
        'oasis.tiers.oase.monthly' => 9.5,
        'oasis.tiers.maezen.monthly' => 21,
        'oasis.year_months' => 9,
        'oasis.vat_rate' => 7,
    ]);
    $this->get(route('oasis.preise'))->assertOk()
        ->assertSee('data-month="7,25 €" data-year="65,25 €"', false)
        ->assertSee('data-month="9,50 €" data-year="85,50 €"', false)
        ->assertSee('data-month="ab 21 €" data-year="ab 189 €"', false)
        ->assertSee('rund 24 Cent am Tag')->assertSee('rund 18 Cent am Tag')
        ->assertSee('3 Monate geschenkt')->assertSee('inklusive 7 % Umsatzsteuer')
        ->assertDontSee('data-month="3 €"', false)->assertDontSee('data-year="50 €"', false);

    config(['oasis.checkout_enabled' => true]);
    $this->get(route('oasis.mitglied'))->assertOk()->assertSee('9,50 €')
        ->assertSee('data-month="7,25 € / Monat"', false)
        ->assertSee('data-year="65,25 € / Jahr"', false);
});

it('renders operator, contact, founder and service placeholders from changed config', function () {
    config([
        'oasis.operator.name' => 'Configured Operator', 'oasis.mail.contact' => 'contact@example.tld',
        'oasis.mail.press' => 'press@example.tld', 'oasis.mail.privacy' => 'privacy@example.tld',
        'oasis.founder.name' => 'Configured Founder', 'oasis.founder.text' => 'Configured founder text',
        'oasis.server_location' => 'Configured server region', 'oasis.apps' => 'Configured mobile apps',
        'oasis.domain' => 'oasis.example.tld', 'oasis.support_reply_days' => 8,
        'oasis.founding.seats' => 4321,
    ]);
    $this->get(route('oasis.manifest'))->assertOk()->assertSee('Configured Founder')
        ->assertSee('Configured founder text')->assertSee('press@example.tld')->assertSee('Configured Operator');
    $this->get(route('oasis.hilfe'))->assertOk()->assertSee('contact@example.tld')->assertSee('privacy@example.tld')
        ->assertSee('Configured server region')->assertSee('8 Werktagen');
    $this->get(route('oasis.funktionen'))->assertOk()->assertSee('Configured mobile apps')->assertSee('oasis.example.tld');
    $this->get(route('oasis.preise'))->assertOk()->assertSee('4.321 Mitglieder');
});

it('does not invent transparency figures and calculates published coverage from configured costs', function () {
    config([
        'oasis.transparency.members' => null, 'oasis.transparency.costs' => null,
        'oasis.transparency.income' => null, 'oasis.transparency.sponsored_seats' => null,
        'oasis.transparency.cost_items' => ['Hosting' => null], 'oasis.transparency.votes' => [],
    ]);
    $this->get(route('oasis.transparenz'))->assertOk()->assertSee('Veröffentlichen wir ab dem Start')
        ->assertDontSee('60 %')->assertDontSee('35 %')->assertDontSee('Antwortmöglichkeiten');

    config([
        'oasis.transparency.as_of' => 'Configured month', 'oasis.transparency.members' => 1234,
        'oasis.transparency.cost_items' => ['Hosting' => 200, 'Moderation' => 300],
        'oasis.transparency.income' => 375,
    ]);
    $this->get(route('oasis.transparenz'))->assertOk()->assertSee('Configured month')->assertSee('1.234')
        ->assertSee('500 €')->assertSee('375 €')->assertSee('75 %');
});

it('marks the active main-navigation link on each matching page', function ($page) {
    $response = $this->get(route('oasis.'.$page))->assertOk();
    expect($response->getContent())->toContain('href="'.route('oasis.'.$page).'" aria-current="page"');
})->with(['funktionen', 'preise', 'transparenz', 'manifest', 'gemeinsam']);
