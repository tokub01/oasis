<?php

use App\Mail\Membership\MembershipWithdrawnMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Subscription;
use Stripe\Service\InvoiceService;
use Stripe\Service\RefundService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

test('withdrawal confirmation renders for subscriptions within 14 days', function () {
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
        'created_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('app.membership.withdraw.confirm'))
        ->assertOk()
        ->assertSee('Mitgliedschaft widerrufen')
        ->assertSee('14 Tagen')
        ->assertSee('Jetzt widerrufen');
});

test('withdrawal boundary: day 14 is accepted, day 15 is rejected', function () {
    $userDay14 = User::factory()->create(['stripe_id' => 'cus_day14']);
    Subscription::create([
        'user_id' => $userDay14->id,
        'type' => 'oase',
        'stripe_id' => 'sub_day14',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase_monthly',
        'quantity' => 1,
        'created_at' => now()->subDays(14)->addHours(2), // Exactly 14 days ago, within endOfDay
    ]);

    $this->actingAs($userDay14)
        ->get(route('app.membership.withdraw.confirm'))
        ->assertOk();

    $userDay15 = User::factory()->create(['stripe_id' => 'cus_day15']);
    Subscription::create([
        'user_id' => $userDay15->id,
        'type' => 'oase',
        'stripe_id' => 'sub_day15',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase_monthly',
        'quantity' => 1,
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
        'stripe_id' => 'cus_test123',
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'oase',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase_monthly',
        'quantity' => 1,
        'created_at' => now()->subDays(3),
    ]);

    $stripeMock = Mockery::mock(StripeClient::class);
    $subService = Mockery::mock(SubscriptionService::class);
    $invoiceService = Mockery::mock(InvoiceService::class);
    $refundService = Mockery::mock(RefundService::class);

    $stripeMock->subscriptions = $subService;
    $stripeMock->invoices = $invoiceService;
    $stripeMock->refunds = $refundService;
    $stripeMock->shouldReceive('__get')->with('subscriptions')->andReturn($subService);
    $stripeMock->shouldReceive('__get')->with('invoices')->andReturn($invoiceService);
    $stripeMock->shouldReceive('__get')->with('refunds')->andReturn($refundService);

    // Expect subscription cancelNow call
    $subService->shouldReceive('cancel')
        ->once()
        ->with('sub_test123', ['prorate' => true])
        ->andReturn((object) ['status' => 'canceled']);

    // Expect invoice lookup for this subscription
    $invoiceService->shouldReceive('all')
        ->once()
        ->with(['subscription' => 'sub_test123', 'status' => 'paid', 'limit' => 10])
        ->andReturn((object) [
            'data' => [
                (object) ['id' => 'in_123', 'payment_intent' => 'pi_paid_123'],
            ],
        ]);

    // Expect refund creation for paid invoice payment intent (§ 357 Abs. 1 BGB)
    $refundService->shouldReceive('create')
        ->once()
        ->with(['payment_intent' => 'pi_paid_123'])
        ->andReturn((object) ['id' => 're_test_refund']);

    $this->app->bind(StripeClient::class, fn () => $stripeMock);

    $this->actingAs($user)
        ->post(route('app.membership.withdraw'))
        ->assertRedirect(route('app.membership'))
        ->assertSessionHas('status', 'Dein Widerruf wurde erfolgreich erfasst. Deine Mitgliedschaft wurde mit sofortiger Wirkung beendet.');

    $subscription->refresh();
    expect($subscription->ends_at)->not->toBeNull()
        ->and($subscription->stripe_status)->toBe('canceled');

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
