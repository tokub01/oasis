<?php

/* /.well-known/security.txt (RFC 9116) and the disclosure section of the Sicherheit page. */

beforeEach(fn () => $this->withoutVite());

it('serves a valid security.txt that never expires stale', function () {
    config(['oasis.mail.security' => 'security@example.tld']);
    $this->freezeTime();

    $response = $this->get('/.well-known/security.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    $body = $response->getContent();

    expect($body)->toContain("Contact: mailto:security@example.tld\n")
        ->toContain('Expires: '.now()->utc()->addDays(180)->startOfDay()->format('Y-m-d\TH:i:s\Z'))
        ->toContain('Canonical: '.url('/.well-known/security.txt'))
        ->toContain('Policy: '.route('oasis.sicherheit').'#luecke-titel')
        ->toContain('Preferred-Languages: de, en');
});

it('explains responsible disclosure on the Sicherheit page', function () {
    config(['oasis.mail.security' => 'security@example.tld']);

    $this->get(route('oasis.sicherheit'))->assertOk()
        ->assertSee('Sicherheitslücke gefunden?')->assertSee('security@example.tld')
        ->assertSee('keine rechtlichen Schritte')->assertSee(route('oasis.security-txt'), false);
});
