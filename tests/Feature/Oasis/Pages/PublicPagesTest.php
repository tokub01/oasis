<?php

/*
 * Independent public-page contract checks against PROMPT.md: routes, accessibility, config and truthful previews.
 * Vite is disabled so these feature assertions do not depend on a frontend build.
 */

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    config(['oasis.demo_pages' => true, 'oasis.checkout_enabled' => false]);
});

/** Resolve the two auth pages through Fortify while preserving public-page dataset labels. */
function oasisPublicPageUrl(string $page, array $parameters = []): string
{
    return route(match ($page) {
        'testen' => 'register',
        'anmelden' => 'login',
        default => 'oasis.'.$page,
    }, $parameters);
}

dataset('oasis public pages', [
    'home' => ['home', [], 'Social Media, das dir'],
    'features' => ['funktionen', [], 'Quelle'],
    'prices' => ['preise', [], 'Dieselbe Oase für alle.'],
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
    $response = $this->get(oasisPublicPageUrl($page, $parameters))->assertOk()->assertSee($copy);
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
    foreach (['home', 'funktionen', 'preise', 'manifest', 'hilfe', 'testen', 'anmelden',
        'entdecken', 'gemeinsam', 'gruendung', 'kuendigen', 'widerrufen', 'melden', 'impressum',
        'datenschutz', 'agb', 'widerrufsbelehrung', 'regeln'] as $page) {
        $html = $this->get(oasisPublicPageUrl($page))->assertOk()->getContent();
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

it('serves the real sign-in form at the German named login URL', function () {
    expect(route('login', absolute: false))->toBe('/anmelden');
    $this->get(route('login'))->assertOk()->assertSee('action="'.route('login').'"', false);
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
    $this->get(oasisPublicPageUrl($page))->assertOk()->assertSee('47 Tage')
        ->assertDontSee('30 Tage testen')->assertDontSee('30 Tage kostenlos testen');
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
    foreach (['home', 'hilfe'] as $page) {
        $this->get(route('oasis.'.$page))->assertOk()->assertSee('7,25 €')->assertSee('9,50 €');
    }
});

it('links the real password recovery form from the sign-in page', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();
    expect($html)->toContain('href="'.route('password.request').'"');
    expect(preg_match('/<a\b[^>]*>\s*Passwort vergessen\?\s*<\/a>/i', $html))->toBe(1);
    $this->get(route('password.request'))->assertOk()->assertSee('name="email"', false);
});

it('provides a hidden progressive share button with the actual profile URL', function () {
    $html = $this->get(route('oasis.profil', ['handle' => 'mira']))->assertOk()->getContent();
    preg_match('/<button\b[^>]*aria-label="Profil teilen"[^>]*>/i', $html, $button);
    expect($button[0] ?? '')->toContain('type="button"', 'data-oasis="share"', ' hidden',
        'data-url="'.route('oasis.profil', ['handle' => 'mira']).'"');
    expect(preg_match('/<a\b[^>]*aria-label="Profil teilen"/i', $html))->toBe(0);
});

it('marks unavailable sample tabs without links that reload the current view', function () {
    $profile = $this->get(route('oasis.profil', ['handle' => 'mira']))->assertOk()->getContent();
    foreach (['Antworten', 'Oasen'] as $label) {
        expect($profile)->toMatch('/<span\b[^>]*aria-disabled="true"[^>]*title="In der Beispielansicht nicht verfügbar"[^>]*>\s*'.$label.'\s*<\/span>/i');
    }
    $discover = $this->get(route('oasis.entdecken'))->assertOk()->getContent();
    expect($discover)->toMatch('/<span\b[^>]*aria-disabled="true"[^>]*title="In der Beispielansicht nicht verfügbar"[^>]*>\s*Neu\s*<\/span>/i');
});

it('only describes promises as codified when the corresponding config flag is enabled', function () {
    config(['oasis.promises_codified' => false, 'oasis.statutes' => 'Configured statutes']);
    $this->get(route('oasis.manifest'))->assertOk()->assertSee('Wird festgeschrieben:')
        ->assertSee('Configured statutes')->assertDontSee('Diese Versprechen stehen in unseren');

    config(['oasis.promises_codified' => true]);
    $this->get(route('oasis.manifest'))->assertOk()->assertSee('Festgeschrieben:')
        ->assertSee('Diese Versprechen stehen in unseren')->assertSee('Configured statutes')
        ->assertDontSee('Wird festgeschrieben:');
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

it('marks the active main-navigation link on each matching page', function ($page) {
    $response = $this->get(route('oasis.'.$page))->assertOk();
    expect($response->getContent())->toContain('href="'.route('oasis.'.$page).'" wire:navigate aria-current="page"');
})->with(['funktionen', 'preise', 'sicherheit', 'manifest', 'gemeinsam']);

it('sends signed-in members from login and registration into the app, never in a circle', function (string $page) {
    $this->actingAs(User::factory()->create())->get(route($page))->assertRedirect(route('app.home'));
})->with(['login', 'register']);

it('offers signed-in members the way into the app instead of login and trial', function () {
    $this->get(route('oasis.home'))->assertSee(route('login'), false)->assertDontSee('>Zur App<', false);
    $this->actingAs(User::factory()->create())->get(route('oasis.home'))->assertOk()
        ->assertSee('>Zur App<', false)->assertDontSee('href="'.route('login').'"', false);
});

it('explains privacy and security with the same providers as the privacy policy, never invented ones', function () {
    config(['oasis.privacy.hosting' => 'Configured Hosting GmbH', 'oasis.privacy.mail_provider' => null]);

    $this->get(route('oasis.sicherheit'))->assertOk()
        ->assertSee('Datenschutz &amp; Sicherheit', false)->assertSee('Ende-zu-Ende verschlüsselt')
        ->assertSee('Configured Hosting GmbH')->assertSee('legen wir vor dem Start fest')
        ->assertSee(config('oasis.privacy.ai_provider'))->assertSee(route('oasis.datenschutz'), false);
    $this->get(route('oasis.home'))->assertSee(route('oasis.sicherheit'), false);
});

it('no longer offers a public transparency page', function () {
    $this->get('/transparenz')->assertNotFound();
    $this->get(route('oasis.home'))->assertDontSee('/transparenz', false);
});
