<?php

/* Admin statistics: money received per calendar month (German time), browsable month by month. */

use App\Enums\Role;
use App\Livewire\Admin\Dashboard;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Queries\Admin\AdminStats;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00', 'Europe/Berlin'));
});

function revenuePayment(string $paidAt, int $cents, array $attributes = []): MembershipPayment
{
    return MembershipPayment::create(array_merge(['user_id' => N::member()->id, 'mollie_payment_id' => 'tr_'.uniqid(),
        'kind' => 'membership', 'tier' => 'oase', 'rhythm' => 'monat', 'amount' => $cents, 'status' => 'paid',
        'paid_at' => CarbonImmutable::parse($paidAt, 'Europe/Berlin')->utc()], $attributes));
}

it('sums paid money of the calendar month in German time, without refunds, open payments and confirmations', function () {
    revenuePayment('2026-10-01 00:30', 500);          // 30.09. 22:30 UTC, but already October in Germany
    revenuePayment('2026-10-16 18:00', 5000);
    revenuePayment('2026-09-30 23:30', 700);          // September in Germany
    revenuePayment('2026-10-10 10:00', 900, ['refunded' => true]);
    revenuePayment('2026-10-11 10:00', 100, ['kind' => 'mandate']);
    revenuePayment('2026-10-12 10:00', 300, ['status' => 'open']);

    $stats = app(AdminStats::class);
    expect($stats->revenue(CarbonImmutable::parse('2026-10-17', 'Europe/Berlin')))->toBe(['cents' => 5500, 'payments' => 2])
        ->and($stats->revenue(CarbonImmutable::parse('2026-09-05', 'Europe/Berlin')))->toBe(['cents' => 700, 'payments' => 1]);
});

it('shows this month on the dashboard and browses back and forth, never into the future', function () {
    revenuePayment('2026-10-16 18:00', 5000);
    revenuePayment('2026-09-10 10:00', 1200);
    $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);

    Livewire::actingAs($admin)->test(Dashboard::class)
        ->assertSee('Diesen Monat eingenommen')->assertSee('01.10.–31.10.2026')->assertSee('50,00')->assertSee('aus 1 Zahlung')
        ->assertSee('Vormonat: 12,00')->assertDontSee('Nächster Monat')
        ->call('shiftMonth', -1)->assertSet('month', '2026-09')
        ->assertSee('Eingenommen im September 2026')->assertSee('12,00')->assertSee('Nächster Monat')
        ->call('shiftMonth', 1)->assertSet('month', '')->assertSee('Diesen Monat eingenommen')
        ->call('shiftMonth', 1)->assertSet('month', '')
        ->set('month', '2099-01')->assertSee('Diesen Monat eingenommen')
        ->set('month', 'kaputt')->assertSee('Diesen Monat eingenommen');
});

it('keeps the revenue card for admins only', function () {
    $moderator = User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);

    $this->actingAs($moderator)->get(route('admin.dashboard'))->assertForbidden();
});
