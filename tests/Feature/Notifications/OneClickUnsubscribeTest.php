<?php

/* List-Unsubscribe with one-click (RFC 8058) for the daily summary and the waitlist: headers, signed POST without CSRF. */

use App\Mail\Notifications\DailyDigest;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Models\Notifications\NotificationPreference;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('sends the one-click headers with the daily summary and switches it off with a signed POST without login', function () {
    $member = N::member();
    NotificationPreference::create(['user_id' => $member->id, 'digest_enabled' => true]);

    $headers = (new DailyDigest(2, $member))->headers()->text;
    expect($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and($headers['List-Unsubscribe'])->toStartWith('<'.route('notifications.digest.one-click', ['user' => $member]).'?')
        ->and($headers['List-Unsubscribe'])->toContain('signature=');

    $this->post(route('notifications.digest.one-click', ['user' => $member]))->assertForbidden();
    $this->post(trim($headers['List-Unsubscribe'], '<>'))->assertOk()->assertSee('ausgeschaltet');
    expect(NotificationPreference::where('user_id', $member->id)->value('digest_enabled'))->toBeFalse();
});

it('switches the summary off from the mail link in two steps, so mail scanners change nothing', function () {
    $member = N::member();
    NotificationPreference::create(['user_id' => $member->id, 'digest_enabled' => true]);
    $link = URL::signedRoute('notifications.digest.unsubscribe', ['user' => $member]);

    $this->get($link)->assertOk()->assertSee('Zusammenfassung abbestellen');
    expect(NotificationPreference::where('user_id', $member->id)->value('digest_enabled'))->toBeTrue();

    $this->post(URL::signedRoute('notifications.digest.confirm', ['user' => $member]))->assertOk()->assertSee('Zusammenfassung abbestellt');
    expect(NotificationPreference::where('user_id', $member->id)->value('digest_enabled'))->toBeFalse();
    $this->get(route('notifications.digest.unsubscribe', ['user' => $member]))->assertForbidden();
});

it('lets mail programs remove a waitlist entry with one click', function () {
    $entry = WaitlistEntry::create(['email' => 'start@example.tld', 'token' => Str::random(40), 'consented_at' => now()]);

    $headers = (new WaitlistConfirmation($entry))->headers()->text;
    expect($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');

    $this->post(route('oasis.waitlist.one-click', ['token' => $entry->token]))->assertForbidden();
    $this->post(trim($headers['List-Unsubscribe'], '<>'))->assertOk();
    $this->assertModelMissing($entry);
});
