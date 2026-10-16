<?php

use App\Livewire\Membership\Cancel;
use App\Mail\Membership\MembershipCancelledMail;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Membership\MollieGateway;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('cancellation confirmation page requires an active subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.membership.cancel.confirm'))
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Du hast derzeit kein aktives Abonnement, das gekündigt werden kann.');
});

test('cancellation confirmation renders § 312k BGB compliant details and button', function () {
    $user = User::factory()->create([
        'mollie_customer_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_test123',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'ends_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('app.membership.cancel.confirm'))
        ->assertOk()
        ->assertSeeLivewire(Cancel::class)
        ->assertSee('Mitgliedschaft kündigen')
        ->assertSee('Oase')
        ->assertSee($user->email)
        ->assertSee('Jetzt kündigen');
});

test('submitting cancellation cancels subscription and sends confirmation email under § 312k BGB', function () {
    Mail::fake();

    $user = User::factory()->create([
        'mollie_customer_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'mollie_id' => 'sub_test123',
        'status' => 'active', 'paid_until' => now()->addMonth(),

        'ends_at' => null,
    ]);

    $gateway = Mockery::mock(MollieGateway::class);
    $gateway->shouldReceive('cancel')->once()->with('cus_test123', 'sub_test123');
    $this->app->instance(MollieGateway::class, $gateway);

    Livewire::actingAs($user)
        ->test(Cancel::class)
        ->call('cancel')
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Deine Kündigung wurde erfolgreich erfasst. Wir haben dir eine Bestätigung per E-Mail geschickt.');

    // Subscription ends_at is set (grace period until period end)
    $subscription->refresh();
    expect($subscription->ends_at)->not->toBeNull();

    // Two emails sent: one to user, one to operator
    Mail::assertSent(MembershipCancelledMail::class, function (MembershipCancelledMail $mail) use ($user) {
        return $mail->hasTo($user->email) && ! $mail->operatorCopy;
    });

    Mail::assertSent(MembershipCancelledMail::class, function (MembershipCancelledMail $mail) {
        return $mail->hasTo(config('oasis.mail.operator')) && $mail->operatorCopy;
    });
});

test('cancellation mail renders Berlin time and full contract details', function () {
    $user = User::factory()->create([
        'display_name' => 'Erika Musterfrau',
        'email' => 'erika@example.org',
    ]);

    $effectiveDate = now()->addDays(20);
    $mail = new MembershipCancelledMail($user, 'Oase', $effectiveDate, cancelledAt: now());

    $rendered = $mail->render();

    expect($rendered)
        ->toContain('Erika Musterfrau')
        ->toContain('erika@example.org')
        ->toContain('Oase')
        ->toContain('Ordentliche Kündigung')
        ->toContain(now()->timezone('Europe/Berlin')->format('d.m.Y'))
        ->toContain($effectiveDate->timezone('Europe/Berlin')->format('d.m.Y'));
});
