<?php

use App\Mail\Membership\MembershipCancelledMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Subscription;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

test('cancellation confirmation page requires an active subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.membership.cancel.confirm'))
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Du hast derzeit kein aktives Abonnement, das gekündigt werden kann.');
});

test('cancellation confirmation renders § 312k BGB compliant details and button', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase_monthly',
        'quantity' => 1,
        'ends_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('app.membership.cancel.confirm'))
        ->assertOk()
        ->assertSee('Mitgliedschaft kündigen')
        ->assertSee('Oase')
        ->assertSee($user->email)
        ->assertSee('Jetzt kündigen');
});

test('submitting cancellation cancels subscription and sends confirmation email under § 312k BGB', function () {
    Mail::fake();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase_monthly',
        'quantity' => 1,
        'ends_at' => null,
    ]);

    $stripeMock = Mockery::mock(StripeClient::class);
    $subService = Mockery::mock(SubscriptionService::class);
    $stripeMock->subscriptions = $subService;
    $stripeMock->shouldReceive('__get')->with('subscriptions')->andReturn($subService);

    $subService->shouldReceive('update')
        ->once()
        ->with('sub_test123', ['cancel_at_period_end' => true])
        ->andReturn((object) [
            'status' => 'active',
            'current_period_end' => now()->addDays(20)->timestamp,
            'cancel_at' => null,
        ]);

    $this->app->bind(StripeClient::class, fn () => $stripeMock);

    $this->actingAs($user)
        ->post(route('app.membership.cancel'))
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
