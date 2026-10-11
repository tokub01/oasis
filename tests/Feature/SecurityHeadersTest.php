<?php

use App\Enums\Role;
use App\Models\User;

/* Stage 7: security headers and the nonce-based Content-Security-Policy on public, app and staff pages. */

it('sends a strict CSP and security headers on public pages', function () {
    $response = $this->get(route('oasis.home'))->assertOk();
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain('form-action \'self\' https://checkout.stripe.com https://billing.stripe.com')
        ->toContain('wss://ws-eu.pusher.com')
        ->not->toContain("'unsafe-eval'");
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeaderMissing('Strict-Transport-Security');
});

it('puts the per-request nonce on every script tag and into the policy', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('app.chat'))->assertOk();
    preg_match("/'nonce-([^']+)'/", $html->headers->get('Content-Security-Policy'), $match);

    expect($match[1] ?? null)->not->toBeNull();
    preg_match_all('/<script\b[^>]*>/', $html->getContent(), $scripts);
    expect($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$match[1].'"');
    }
});

it('runs Livewire with the CSP-safe Alpine build on staff pages', function () {
    $staff = User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);
    $this->actingAs($staff)->get(route('moderation.index'))->assertOk();

    expect(config('livewire.csp_safe'))->toBeTrue();
});

it('adds HSTS on HTTPS requests', function () {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
