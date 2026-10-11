<?php

use App\Mail\Membership\TrialEndingReminderMail;
use App\Models\GiftMembership;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Subscription;

test('sends reminder email to users whose trial ends within 2 days and is idempotent', function () {
    Mail::fake();

    // User A: trial ends in 36 hours -> should receive email
    $userA = User::factory()->create([
        'trial_ends_at' => now()->addHours(36),
        'trial_reminder_sent_at' => null,
    ]);

    // User B: trial ends in 5 days -> should NOT receive email
    $userB = User::factory()->create([
        'trial_ends_at' => now()->addDays(5),
        'trial_reminder_sent_at' => null,
    ]);

    // User C: trial already expired -> should NOT receive email
    $userC = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'trial_reminder_sent_at' => null,
    ]);

    // User D: trial ends in 36 hours, but already has subscription -> should NOT receive email
    $userD = User::factory()->create([
        'trial_ends_at' => now()->addHours(36),
        'trial_reminder_sent_at' => null,
    ]);
    Subscription::create([
        'user_id' => $userD->id,
        'type' => 'oase',
        'stripe_id' => 'sub_userD',
        'stripe_status' => 'active',
        'stripe_price' => 'price_oase',
    ]);

    // User E: trial ends in 36 hours, but has active gift -> should NOT receive email
    $userE = User::factory()->create([
        'trial_ends_at' => now()->addHours(36),
        'trial_reminder_sent_at' => null,
    ]);
    GiftMembership::factory()->redeemed($userE)->create();

    // Run command
    $this->artisan('oasis:trial-reminders')
        ->assertSuccessful();

    // Verify only User A received email
    Mail::assertSent(TrialEndingReminderMail::class, function (TrialEndingReminderMail $mail) use ($userA) {
        return $mail->hasTo($userA->email);
    });

    Mail::assertNotSent(TrialEndingReminderMail::class, function (TrialEndingReminderMail $mail) use ($userB, $userC, $userD, $userE) {
        return $mail->hasTo($userB->email)
            || $mail->hasTo($userC->email)
            || $mail->hasTo($userD->email)
            || $mail->hasTo($userE->email);
    });

    $userA->refresh();
    expect($userA->trial_reminder_sent_at)->not->toBeNull();

    // Run command a second time: User A must NOT receive a second email (idempotent)
    Mail::fake();
    $this->artisan('oasis:trial-reminders')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

test('trial reminder mailable renders content correctly', function () {
    $user = User::factory()->create([
        'display_name' => 'Alex Tester',
        'trial_ends_at' => now()->addDays(2),
    ]);

    $mailable = new TrialEndingReminderMail($user);
    $rendered = $mailable->render();

    expect($mailable->envelope()->subject)->toBe('Dein kostenloser Oasis-Test endet in 2 Tagen')
        ->and($mailable->content()->view)->toBe('membership.mail.trial_ending')
        ->and($rendered)->toContain('Hallo Alex Tester')
        ->and($rendered)->toContain('dein kostenloser 30-tägiger Test bei Oasis endet in zwei Tagen');
});
