<?php

/*
 * AGB draft checks: config-driven contract facts and truthful planned/codified promises.
 * Vite is disabled because these assertions concern server-rendered legal copy.
 */

beforeEach(function () {
    $this->withoutVite();
});

it('renders the terms draft with configured operator, periods and exact membership prices', function () {
    config([
        'oasis.operator.legal_name' => 'Test Legal Operator',
        'oasis.operator.legal_form' => 'Test Legal Form',
        'oasis.operator.street' => 'Teststraße 42',
        'oasis.operator.city' => '12345 Teststadt',
        'oasis.operator.country' => 'Testland',
        'oasis.trial_days' => 47,
        'oasis.retention_days' => 63,
        'oasis.founding.seats' => 4321,
        'oasis.year_months' => 9,
        'oasis.vat_rate' => 7,
        'oasis.tiers.solidar' => ['name' => 'Test Solidar', 'monthly' => 7.25, 'min' => false],
        'oasis.tiers.oase' => ['name' => 'Test Oase', 'monthly' => 9.5, 'min' => false],
        'oasis.tiers.maezen' => ['name' => 'Test Mäzen', 'monthly' => 21.75, 'min' => true],
    ]);

    $this->get(route('oasis.agb'))->assertOk()
        ->assertSee('Entwurf – vor dem Start anwaltlich prüfen lassen')
        ->assertSee('Test Legal Operator')->assertSee('Test Legal Form')
        ->assertSee('Teststraße 42')->assertSee('12345 Teststadt')->assertSee('Testland')
        ->assertSee('47 Tage')->assertSee('63 Tage')->assertSee('4.321 Mitglieder')
        ->assertSee('7 % Umsatzsteuer')->assertSee('9 Monatsbeiträgen')
        ->assertSee('Test Solidar: 7,25 € pro Monat oder 65,25 € pro Jahr.')
        ->assertSee('Test Oase: 9,50 € pro Monat oder 85,50 € pro Jahr.')
        ->assertSee('Test Mäzen: ab 21,75 € pro Monat oder ab 195,75 € pro Jahr.')
        ->assertSee(route('oasis.kuendigen'), false)
        ->assertSee(route('oasis.widerrufsbelehrung'), false)
        ->assertSee(route('oasis.widerrufen'), false)
        ->assertSee(route('oasis.regeln'), false)
        ->assertSee(route('oasis.melden'), false)
        ->assertDontSee('[Text]')->assertDontSee('[Stufen, Abrechnung');
});

it('only makes core promises contractual when their config flag is enabled', function () {
    config(['oasis.promises_codified' => false]);
    $this->get(route('oasis.agb'))->assertOk()
        ->assertSee('Wird festgeschrieben:')
        ->assertSee('Die vertragliche Festschreibung ist noch nicht abgeschlossen.')
        ->assertDontSee('sind verbindlicher Bestandteil dieser Bedingungen.')
        ->assertDontSee('ist zusätzlich die Zustimmung der Mitglieder erforderlich.');

    config(['oasis.promises_codified' => true]);
    $this->get(route('oasis.agb'))->assertOk()
        ->assertSee('Festgeschrieben:')
        ->assertSee('sind verbindlicher Bestandteil dieser Bedingungen.')
        ->assertSee('ist zusätzlich die Zustimmung der Mitglieder erforderlich.')
        ->assertDontSee('Wird festgeschrieben:')
        ->assertDontSee('Die vertragliche Festschreibung ist noch nicht abgeschlossen.');
});
