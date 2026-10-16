<?php

use App\Livewire\Membership\Withdraw;
use App\Mail\Membership\MembershipWithdrawnMail;
use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Membership\MollieGateway;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('withdrawal confirmation renders for subscriptions within 14 days', function () {
    $user = User::factory()->create([
        'mollie_customer_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_test123',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'created_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('app.membership.withdraw.confirm'))
        ->assertOk()
        ->assertSeeLivewire(Withdraw::class)
        ->assertSee('Mitgliedschaft widerrufen')
        ->assertSee('14 Tagen')
        ->assertSee('Jetzt widerrufen');
});

test('withdrawal boundary: day 14 is accepted, day 15 is rejected', function () {
    $userDay14 = User::factory()->create(['mollie_customer_id' => 'cus_day14']);
    Subscription::create([
        'user_id' => $userDay14->id,
        'type' => 'oase',
        'mollie_id' => 'sub_day14',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'created_at' => now()->subDays(14)->addHours(2), // Exactly 14 days ago, within endOfDay
    ]);

    $this->actingAs($userDay14)
        ->get(route('app.membership.withdraw.confirm'))
        ->assertOk();

    $userDay15 = User::factory()->create(['mollie_customer_id' => 'cus_day15']);
    Subscription::create([
        'user_id' => $userDay15->id,
        'type' => 'oase',
        'mollie_id' => 'sub_day15',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'created_at' => now()->subDays(15), // 15 days ago, past endOfDay of day 14
    ]);

    $this->actingAs($userDay15)
        ->get(route('app.membership.withdraw.confirm'))
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Die gesetzliche 14-tägige Widerrufsfrist für diesen Vertrag ist bereits abgelaufen.');
});

test('submitting withdrawal terminates subscription immediately, refunds invoices and dispatches emails', function () {
    Mail::fake();

    $user = User::factory()->create([
        'mollie_customer_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_test123',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'created_at' => now()->subDays(3),
    ]);

    $gateway = Mockery::mock(MollieGateway::class);
    $gateway->shouldReceive('cancel')->once()->with('cus_test123', 'sub_test123');
    MembershipPayment::create(['user_id' => $user->id, 'subscription_id' => $subscription->id,
        'mollie_payment_id' => 'tr_paid123', 'kind' => 'membership', 'tier' => 'oase', 'rhythm' => 'monat', 'amount' => 500, 'status' => 'paid']);
    $gateway->shouldReceive('refund')->once()->with('tr_paid123', 500);
    $this->app->instance(MollieGateway::class, $gateway);

    Livewire::actingAs($user)
        ->test(Withdraw::class)
        ->call('withdraw')
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Dein Widerruf wurde erfolgreich erfasst. Deine Mitgliedschaft wurde mit sofortiger Wirkung beendet.');

    $subscription->refresh();
    expect($subscription->ends_at)->not->toBeNull()
        ->and($subscription->status)->toBe('canceled');

    Mail::assertSent(MembershipWithdrawnMail::class, function (MembershipWithdrawnMail $mail) use ($user) {
        return $mail->hasTo($user->email) && ! $mail->operatorCopy;
    });

    Mail::assertSent(MembershipWithdrawnMail::class, function (MembershipWithdrawnMail $mail) {
        return $mail->hasTo(config('oasis.mail.operator')) && $mail->operatorCopy;
    });
});

test('membership withdrawn mailable renders correctly for member and operator', function () {
    $user = User::factory()->create([
        'display_name' => 'Charlie Tester',
        'email' => 'charlie@example.com',
    ]);

    $memberMail = new MembershipWithdrawnMail($user, 'Oase');
    $memberRendered = $memberMail->render();

    expect($memberMail->envelope()->subject)->toBe('Bestätigung deines Widerrufs bei Oasis')
        ->and($memberMail->content()->view)->toBe('membership.mail.withdrawn')
        ->and($memberRendered)->toContain('Bestätigung deines Widerrufs')
        ->and($memberRendered)->toContain('Charlie Tester')
        ->and($memberRendered)->toContain('Widerruf innerhalb der 14-tägigen gesetzlichen Widerrufsfrist (§ 355 BGB)')
        ->and($memberRendered)->toContain('Bereits gezahlte Beträge erstatten wir dir unverzüglich auf dein ursprüngliches Zahlungsmittel zurück (§ 357 Abs. 1 BGB)');

    $operatorMail = new MembershipWithdrawnMail($user, 'Oase', operatorCopy: true);
    $operatorRendered = $operatorMail->render();

    expect($operatorMail->envelope()->subject)->toContain('Kopie: Widerruf von Charlie Tester')
        ->and($operatorRendered)->toContain('interne Betreiberkopie des Widerrufs');
});
