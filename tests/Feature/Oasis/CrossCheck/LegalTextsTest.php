<?php

/*
 * Checks the privacy policy and the withdrawal instructions: they must name the configured operator, describe
 * only what the site really does, keep open facts visible as "[noch festzulegen]" and follow the statutory model.
 */

beforeEach(function () {
    $this->withoutVite();
});

describe('Datenschutzerklärung', function () {
    it('names the configured controller and contact', function () {
        config([
            'oasis.operator.legal_name' => 'Configured Operator',
            'oasis.operator.street' => 'Configured Street 1',
            'oasis.mail.privacy' => 'privacy@example.tld',
        ]);

        $this->get(route('oasis.datenschutz'))->assertOk()
            ->assertSee('Configured Operator')
            ->assertSee('Configured Street 1')
            ->assertSee('privacy@example.tld');
    });

    it('covers every form the site offers and the double opt-in', function () {
        $this->get(route('oasis.datenschutz'))->assertOk()
            ->assertSee('Double-Opt-in')
            ->assertSee('Verträge hier kündigen')
            ->assertSee('Inhalte melden')
            ->assertSee('Anfragen von Organisationen')
            ->assertSee('Art. 77 DSGVO');
    });

    it('shows open facts instead of inventing them', function () {
        config(['oasis.privacy.server_log_days' => null, 'oasis.privacy.mail_provider' => null]);

        $this->get(route('oasis.datenschutz'))->assertOk()->assertSee('[noch festzulegen]');
    });

    it('renders configured facts', function () {
        config([
            'oasis.privacy.server_log_days' => 14,
            'oasis.privacy.mail_provider' => 'Configured Mail Provider',
        ]);

        $this->get(route('oasis.datenschutz'))->assertOk()
            ->assertSee('nach 14 Tagen')
            ->assertSee('Configured Mail Provider');
    });

    it('does not claim any tracking or third-party content', function () {
        $html = $this->get(route('oasis.datenschutz'))->assertOk()->getContent();

        expect($html)->not->toContain('Google Analytics')->not->toContain('googleapis');
    });
});

describe('Widerrufsbelehrung', function () {
    it('uses the statutory model wording and the configured operator', function () {
        config(['oasis.operator.legal_name' => 'Configured Operator']);

        $this->get(route('oasis.widerrufsbelehrung'))->assertOk()
            ->assertSee('binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen')
            ->assertSee('Folgen des Widerrufs')
            ->assertSee('Muster-Widerrufsformular')
            ->assertSee('Configured Operator');
    });

    it('links to the electronic withdrawal form', function () {
        $this->get(route('oasis.widerrufsbelehrung'))->assertOk()
            ->assertSee(route('oasis.widerrufen'), false)
            ->assertSee('Vertrag widerrufen');
    });
});
