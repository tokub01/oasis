<?php

use App\Livewire\Membership\Checkout;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Livewire\Livewire;

test('membership public page returns 404 when checkout_enabled is false', function () {
    config()->set('oasis.checkout_enabled', false);

    $this->get('/mitglied-werden')->assertNotFound();
    Livewire::test(Checkout::class)->assertNotFound();
});

test('membership public page renders when checkout_enabled is true', function () {
    config()->set('oasis.checkout_enabled', true);

    $this->get('/mitglied-werden')
        ->assertOk()
        ->assertSeeLivewire(Checkout::class)
        ->assertSee('Bleib in der Oase.')
        ->assertSee('Zahlungspflichtig bestellen');
});

test('checkout redirects unauthenticated user to sign up', function () {
    config()->set('oasis.checkout_enabled', true);

    Livewire::test(Checkout::class)
        ->set('stufe', 'oase')
        ->set('rhythmus', 'monat')
        ->call('checkout')
        ->assertRedirect(route('register'))
        ->assertSessionHas('status', 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.');
});

test('checkout validates inputs correctly', function () {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Checkout::class)
        ->set('stufe', 'maezen')
        ->set('rhythmus', 'monat')
        ->set('betrag', '5')
        ->call('checkout')
        ->assertHasErrors(['betrag']);
});

test('checkout updates reactive order summary for tier, rhythm, payment method and custom Mäzen amount', function () {
    config()->set('oasis.checkout_enabled', true);

    Livewire::test(Checkout::class)
        ->assertSee("5,00\u{00A0}€")
        ->assertSee("darin 0,80\u{00A0}€")
        ->set('rhythmus', 'jahr')
        ->assertSee('Jährlich')
        ->assertSee("50,00\u{00A0}€")
        ->assertSee("darin 7,98\u{00A0}€")
        ->call('selectMaezen')
        ->assertSet('stufe', 'maezen')
        ->assertSee("120,00\u{00A0}€")
        ->assertSee("darin 19,16\u{00A0}€")
        ->set('betrag', '25')
        ->assertSee("250,00\u{00A0}€")
        ->assertSee("darin 39,92\u{00A0}€")
        ->set('betrag', '8')
        ->assertSee("Mindestens 12\u{00A0}€")
        ->set('zahlart', 'sepa')
        ->assertSee('SEPA-Lastschrift');
});

test('checkout initiates Mollie Checkout session and redirects authenticated user', function () {
    config()->set('oasis.checkout_enabled', true);
    $user = User::factory()->create();

    $serviceMock = Mockery::mock(MollieMembershipService::class);
    $serviceMock->shouldReceive('createSubscriptionCheckout')
        ->once()
        ->with(
            Mockery::on(fn ($u) => $u->id === $user->id),
            'oase',
            'monat',
            null,
            'sepa'
        )
        ->andReturn('https://www.mollie.com/checkout/test123');

    $this->app->instance(MollieMembershipService::class, $serviceMock);

    Livewire::actingAs($user)
        ->test(Checkout::class)
        ->set('stufe', 'oase')
        ->set('rhythmus', 'monat')
        ->set('zahlart', 'sepa')
        ->call('checkout')
        ->assertRedirect('https://www.mollie.com/checkout/test123');
});

test('checkout rejects invalid custom amounts with inline feedback before payment', function (string $amount) {
    config(['oasis.checkout_enabled' => true]);
    $service = Mockery::mock(MollieMembershipService::class);
    $service->shouldNotReceive('createSubscriptionCheckout');
    app()->instance(MollieMembershipService::class, $service);

    Livewire::actingAs(User::factory()->create())->test(Checkout::class)->set('betrag', $amount)->set('stufe', 'maezen')->assertSet('betrag', $amount)->assertSet('stufe', 'maezen')
        ->call('checkout')->assertHasErrors('betrag');
})->with(['fractional' => '12.50', 'comma' => '12,50', 'empty' => '', 'invalid' => 'ungueltig', 'below minimum' => '8']);

test('checkout ignores a stale custom amount when a fixed-price tier is selected', function () {
    config(['oasis.checkout_enabled' => true]);
    $user = User::factory()->create();
    $service = Mockery::mock(MollieMembershipService::class);
    $service->shouldReceive('createSubscriptionCheckout')->once()->withArgs(fn ($actor, $tier, $rhythm, $amount, $method) => $actor->is($user) && $tier === 'solidar' && $rhythm === 'monat' && $amount === null && $method === 'karte')
        ->andReturn('https://checkout.mollie.tld/test');
    app()->instance(MollieMembershipService::class, $service);

    Livewire::actingAs($user)->test(Checkout::class)->set('betrag', '5')->set('stufe', 'solidar')
        ->call('checkout')->assertHasNoErrors()->assertRedirect('https://checkout.mollie.tld/test');
});

test('checkout live summaries preserve tier rhythm and included-tax calculations', function (string $tier, string $rhythm, float $total, float $vat) {
    config(['oasis.checkout_enabled' => true]);
    $component = Livewire::test(Checkout::class)->set('stufe', $tier)->set('rhythmus', $rhythm);

    expect($component->viewData('summaryTotal'))->toBe($total);
    expect(round($component->viewData('summaryVat'), 2))->toBe($vat);
})->with([
    ['solidar', 'monat', 3.0, 0.48], ['solidar', 'jahr', 30.0, 4.79],
    ['oase', 'monat', 5.0, 0.80], ['oase', 'jahr', 50.0, 7.98],
    ['maezen', 'monat', 12.0, 1.92], ['maezen', 'jahr', 120.0, 19.16],
]);
