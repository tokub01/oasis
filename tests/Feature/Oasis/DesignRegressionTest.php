<?php

/*
 * Regression checks for the design review of 17 October 2026 (shell, header, footer, prices).
 * Only behaviour that markup alone guarantees is checked here; the look itself is reviewed with screenshots.
 */

use App\Models\User;
use App\Support\OasisFormat;

beforeEach(function () {
    $this->withoutVite();
});

it('keeps amount and euro sign together with a no-break space', function () {
    expect(OasisFormat::euro(5))->toBe("5\u{00A0}€")
        ->and(OasisFormat::euro(7.25))->toBe("7,25\u{00A0}€")
        ->and(OasisFormat::money(1234.5))->toBe("1.234,50\u{00A0}€");
});

it('offers the public navigation in a labelled menu that works without JavaScript', function () {
    $html = $this->get(route('oasis.preise'))->assertOk()->getContent();

    expect($html)->toContain('<summary aria-label="Menü"')
        ->and(substr_count($html, 'aria-label="Hauptnavigation"'))->toBe(2);
});

it('links the contact address in the footer', function () {
    $this->get(route('oasis.home'))->assertOk()
        ->assertSee('href="mailto:'.config('oasis.mail.contact').'"', false);
});

it('drops the sidebar search field on the search page only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('network.discover'))->assertOk()->assertSee('id="app-search"', false);
    $this->actingAs($user)->get(route('search.index', ['q' => 'tomaten']))->assertOk()->assertDontSee('id="app-search"', false);
});
