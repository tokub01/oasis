<?php

use App\Models\GiftMembership;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

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

test('billing management renders without a provider customer', function () {
    $this->actingAs(User::factory()->create())->get(route('app.membership.portal'))
        ->assertOk()->assertSee('Noch keine bezahlten Beiträge vorhanden.');
});

test('billing management offers a refundable mandate update through a protected form', function () {
    $this->actingAs(User::factory()->create(['mollie_customer_id' => 'cst_test123']))
        ->get(route('app.membership.portal'))->assertOk()->assertSee('Zahlungsmittel ändern')->assertSee('1 €');
});

test('purchased voucher codes are shown only in the purchasers membership area', function () {
    $buyer = User::factory()->create();
    $gift = GiftMembership::factory()->create(['purchaser_user_id' => $buyer->id]);
    $this->actingAs($buyer)->get(route('app.membership'))->assertOk()->assertSee($gift->code);
    $this->actingAs(User::factory()->create())->get(route('app.membership'))->assertOk()->assertDontSee($gift->code);
});

test('membership overview offers withdrawal through the end of day fourteen', function (int $days, bool $eligible) {
    $this->travelTo(Carbon::parse('2026-10-16 16:00:00 UTC'));
    $user = User::factory()->create();
    Subscription::create(['user_id' => $user->id, 'type' => 'oase', 'status' => 'active',
        'paid_until' => now()->addMonth(), 'created_at' => now()->subDays($days)->startOfDay()->addHours(10)]);

    $this->actingAs($user)->get(route('app.membership'))->assertOk()->assertViewHas('isEligibleForWithdrawal', $eligible);
})->with([[14, true], [15, false]]);
