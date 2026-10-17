<?php

/* Erklärung zur Barrierefreiheit (§ 14 BFSG): reachable from every page, honest about limits, open values visible. */

beforeEach(fn () => $this->withoutVite());

it('states standard, state, limits, feedback and the authority', function () {
    config(['oasis.accessibility.authority' => null]);

    $this->get(route('oasis.barrierefreiheit'))->assertOk()
        ->assertSee('WCAG')->assertSee('EN 301 549')->assertSee('weitgehend barrierefrei')
        ->assertSee('Was noch nicht barrierefrei ist')->assertSee('Mollie')
        ->assertSee(config('oasis.mail.contact'))->assertSee('[noch festzulegen]');

    config(['oasis.accessibility.authority' => 'Configured Authority, Musterstraße 1']);
    $this->get(route('oasis.barrierefreiheit'))->assertSee('Configured Authority, Musterstraße 1')->assertDontSee('[noch festzulegen]');
});

it('is linked in the footer and in the legal navigation', function () {
    $this->get(route('oasis.home'))->assertSee(route('oasis.barrierefreiheit'), false);
    $this->get(route('oasis.impressum'))->assertSee(route('oasis.barrierefreiheit'), false);
});
