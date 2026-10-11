<?php

use App\Models\User;
use App\Services\Membership\StripeMembershipService;

test('membership public page returns 404 when checkout_enabled is false', function () {
    config()->set('oasis.checkout_enabled', false);

    $this->get('/mitglied-werden')->assertNotFound();
    $this->post('/mitglied-werden/checkout')->assertNotFound();
});

test('membership public page renders when checkout_enabled is true', function () {
    config()->set('oasis.checkout_enabled', true);

    $this->get('/mitglied-werden')
        ->assertOk()
        ->assertSee('Bleib in der Oase.')
        ->assertSee('Zahlungspflichtig bestellen');
});

test('checkout redirects unauthenticated user to sign up', function () {
    config()->set('oasis.checkout_enabled', true);

    $this->post('/mitglied-werden/checkout', [
        'stufe' => 'oase',
        'rhythmus' => 'monat',
    ])
        ->assertRedirect(route('register'))
        ->assertSessionHas('status', 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.');
});

test('checkout validates inputs correctly', function () {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/mitglied-werden/checkout', [
            'stufe' => 'maezen',
            'rhythmus' => 'monat',
            'betrag' => 5, // Below 12 € minimum
        ])
        ->assertSessionHasErrors('betrag');
});

test('checkout initiates Stripe Checkout session and redirects authenticated user', function () {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create();

    $serviceMock = Mockery::mock(StripeMembershipService::class);
    $serviceMock->shouldReceive('createSubscriptionCheckout')
        ->once()
        ->with(
            Mockery::on(fn ($u) => $u->id === $user->id),
            'oase',
            'monat',
            null,
            'sepa'
        )
        ->andReturn('https://checkout.stripe.com/pay/cs_test_session_123');

    $this->app->instance(StripeMembershipService::class, $serviceMock);

    $this->actingAs($user)
        ->post('/mitglied-werden/checkout', [
            'stufe' => 'oase',
            'rhythmus' => 'monat',
            'zahlart' => 'sepa',
        ])
        ->assertRedirect('https://checkout.stripe.com/pay/cs_test_session_123');
});
