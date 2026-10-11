<?php

use App\Models\GiftMembership;
use App\Models\User;
use App\Services\Membership\StripeMembershipService;

test('membership page requires authentication and email verification', function () {
    $this->get(route('app.membership'))->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)
        ->get(route('app.membership'))
        ->assertRedirect(route('verification.notice'));
});

test('membership page displays active trial status with remaining days', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(20),
    ]);

    $this->actingAs($user)
        ->get(route('app.membership'))
        ->assertOk()
        ->assertSee('Kostenloser Test')
        ->assertSee('20 Tage')
        ->assertSee('Mitgliedschaft wählen');
});

test('membership page displays read-only notice when trial has expired', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->get(route('app.membership'))
        ->assertOk()
        ->assertSee('Test abgelaufen · Lesemodus')
        ->assertSee('Mitglied werden');
});

test('membership page displays founding member badge when user is a founding member', function () {
    $user = User::factory()->create([
        'founding_member_at' => now(),
        'founding_price_guarantee' => true,
        'trial_ends_at' => now()->addDays(10),
    ]);

    $this->actingAs($user)
        ->get(route('app.membership'))
        ->assertOk()
        ->assertSee('Gründungsmitglied mit Preisgarantie');
});

test('membership page displays active gift membership status', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    GiftMembership::factory()->redeemed($user, months: 12)->create([
        'tier' => 'solidar',
    ]);

    $this->actingAs($user)
        ->get(route('app.membership'))
        ->assertOk()
        ->assertSee('Geschenkte Mitgliedschaft')
        ->assertSee('Solidar');
});

test('app navigation links to membership page with heart icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.membership'))
        ->assertOk()
        ->assertSee('Mitgliedschaft')
        ->assertSee(route('app.membership'));
});

test('portal redirects to membership page if user has no stripe id', function () {
    $user = User::factory()->create([
        'stripe_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('app.membership.portal'))
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Du hast bisher keine Zahlungsdaten bei Stripe hinterlegt.');
});

test('portal redirects to billing portal url when user has stripe id', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test123',
    ]);

    $serviceMock = Mockery::mock(StripeMembershipService::class);
    $serviceMock->shouldReceive('getBillingPortalUrl')
        ->once()
        ->with(Mockery::on(fn ($u) => $u->id === $user->id))
        ->andReturn('https://billing.stripe.com/p/session/test123');

    $this->app->instance(StripeMembershipService::class, $serviceMock);

    $response = $this->actingAs($user)
        ->get(route('app.membership.portal'));

    $response->assertRedirect('https://billing.stripe.com/p/session/test123');
});
