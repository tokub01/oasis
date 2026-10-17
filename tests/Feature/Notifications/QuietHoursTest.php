<?php

/* Ruhezeiten and Pause: no hint mails during them, hints still collect, the summary catches up later. */

use App\Enums\Notifications\NoticeKind;
use App\Livewire\Notifications\Settings;
use App\Mail\Notifications\DailyDigest;
use App\Models\Notifications\NotificationPreference;
use App\Notifications\NetworkHint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

/** One unread hint for $user, written now. */
function unreadHint($user): void
{
    $user->notify(new NetworkHint((string) Str::uuid(), ['kind' => NoticeKind::Follow->value, 'actor_id' => N::member()->id, 'post_id' => null, 'excerpt' => '']));
}

it('knows quiet hours across midnight in German time', function (string $berlinTime, bool $quiet) {
    $preference = new NotificationPreference(['quiet_enabled' => true, 'quiet_from' => '22:00:00', 'quiet_until' => '07:00:00']);

    expect($preference->inQuietHours(Carbon::parse('2026-10-20 '.$berlinTime, 'Europe/Berlin')))->toBe($quiet);
})->with([
    ['21:59', false], ['22:00', true], ['23:30', true], ['03:00', true], ['06:59', true], ['07:00', false], ['12:00', false],
]);

it('knows a quiet window within one day and ignores it when switched off', function () {
    $preference = new NotificationPreference(['quiet_enabled' => true, 'quiet_from' => '12:00:00', 'quiet_until' => '14:00:00']);
    expect($preference->inQuietHours(Carbon::parse('2026-10-20 13:00', 'Europe/Berlin')))->toBeTrue()
        ->and($preference->inQuietHours(Carbon::parse('2026-10-20 15:00', 'Europe/Berlin')))->toBeFalse();

    $preference->quiet_enabled = false;
    expect($preference->inQuietHours(Carbon::parse('2026-10-20 13:00', 'Europe/Berlin')))->toBeFalse();
});

it('sends the summary in the first evening hour outside the quiet hours and keeps the hints for it', function () {
    $member = N::member();
    NotificationPreference::create(['user_id' => $member->id, 'digest_enabled' => true, 'quiet_enabled' => true,
        'quiet_from' => '17:00:00', 'quiet_until' => '20:00:00']);

    $this->travelTo(Carbon::parse('2026-10-20 18:05', 'Europe/Berlin'));
    unreadHint($member);
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo(Carbon::parse('2026-10-20 20:05', 'Europe/Berlin'));
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertSent(DailyDigest::class, fn (DailyDigest $mail) => $mail->count === 1);
});

it('sends nothing during a pause and goes on by itself afterwards', function () {
    $member = N::member();
    NotificationPreference::create(['user_id' => $member->id, 'digest_enabled' => true]);
    $this->travelTo(Carbon::parse('2026-10-20 12:00', 'Europe/Berlin'));
    unreadHint($member);

    Livewire::actingAs($member)->test(Settings::class)->call('pause', 3)->assertSee('Pause bis')->assertSee('Pause beenden');
    $this->actingAs($member)->get(route('app.home'))->assertDontSee('(neue Hinweise)');

    $this->travelTo(Carbon::parse('2026-10-21 18:05', 'Europe/Berlin'));
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo(Carbon::parse('2026-10-23 18:05', 'Europe/Berlin'));
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertSent(DailyDigest::class);
    $this->actingAs($member)->get(route('app.home'))->assertSee('(neue Hinweise)');
});

it('saves quiet hours, refuses invalid times and only offers the listed pauses', function () {
    $member = N::member();

    Livewire::actingAs($member)->test(Settings::class)->set('quietEnabled', true)
        ->set('quietFrom', '25:00')->call('save')->assertHasErrors('quietFrom')
        ->set('quietFrom', '22:30')->set('quietUntil', '22:30')->call('save')->assertHasErrors('quietUntil')
        ->set('quietUntil', '06:45')->call('save')->assertHasNoErrors();
    $preference = NotificationPreference::where('user_id', $member->id)->sole();
    expect($preference->quiet_enabled)->toBeTrue()->and(substr($preference->quiet_from, 0, 5))->toBe('22:30');

    Livewire::actingAs($member)->test(Settings::class)->call('pause', 30)->assertStatus(422);
    Livewire::actingAs($member)->test(Settings::class)->call('pause', 1)->call('resume')->assertSee('Die Pause ist beendet.');
    expect($preference->fresh()->paused_until)->toBeNull();
});
