<?php

/* Installable web app (docs/oasis-pwa.md): manifest, service worker, offline page and head tags. */

use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('ships a valid manifest with the app icons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/app')->and($manifest['display'])->toBe('standalone')->and($manifest['lang'])->toBe('de')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');
    foreach ([...$manifest['icons'], ...collect($manifest['shortcuts'])->pluck('icons')->flatten(1)] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('links the manifest on public, member and staff pages', function () {
    $this->get(route('oasis.home'))->assertOk()->assertSee('rel="manifest"', false)->assertSee('name="theme-color"', false);
    $this->actingAs(N::member())->get(route('notifications.settings'))->assertOk()->assertSee('rel="manifest"', false);
});

it('serves an offline page that does not depend on the account', function () {
    $this->get(route('oasis.offline'))->assertOk()->assertSee('Keine Verbindung')->assertSee('noindex', false);
});

it('never caches pages in the service worker', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("request.mode === 'navigate'")->toContain('caches.match(OFFLINE)')
        ->and($worker)->toContain("'/offline'");
});
