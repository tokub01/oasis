<?php

/*
 * Regression coverage for privacy-preserving opt-in, Berlin receipt/calendar time and retainable declarations.
 * Guards § 312k BGB cancellation content, withdrawal receipts and DSA Art. 16 good-faith declarations.
 */

use App\Mail\Oasis\CancellationReceipt;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\ContentReport;
use App\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PublicForms;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    config(['oasis.mail.operator' => 'operator@example.tld', 'oasis.mail.moderation' => 'moderation@example.tld']);
});

it('keeps a confirmed waitlist entry unchanged and gives the same response without sending mail', function () {
    $entry = WaitlistEntry::create([
        'email' => 'member@example.tld', 'group' => 'Original group',
        'consented_at' => now()->subDays(2), 'confirmed_at' => now()->subDay(),
        'token' => str_repeat('c', 64),
    ]);
    $attributes = $entry->fresh()->getAttributes();

    PublicForms::submit('gruendung', [
        'email' => 'MEMBER@example.tld', 'consent' => '1', 'group' => 'Another group',
    ])->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.gruendung'));

    expect($entry->fresh()->getAttributes())->toBe($attributes);
    Mail::assertNothingSent();
    $this->get(route('oasis.bestaetigung.gruendung'))->assertOk()->assertDontSee('Wir haben dir einen Bestätigungslink geschickt');
});

it('resends an unconfirmed opt-in without changing its original consent or token', function () {
    $entry = WaitlistEntry::create([
        'email' => 'member@example.tld', 'consented_at' => now()->subDays(2), 'token' => str_repeat('d', 64),
    ]);
    $attributes = $entry->fresh()->getAttributes();

    PublicForms::submit('gruendung', ['email' => $entry->email, 'consent' => '1'])
        ->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.gruendung'));

    expect($entry->fresh()->getAttributes())->toBe($attributes);
    Mail::assertSent(WaitlistConfirmation::class, 1);
});

it('rejects past cancellation dates with a German error and accepts today', function () {
    $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));
    $payload = [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich', 'timing' => 'datum',
    ];

    PublicForms::submit('kuendigen', [...$payload, 'effective_date' => '2026-11-02'])
        ->assertHasErrors(['form.effective_date' => 'Bitte wähle heute oder ein Datum in der Zukunft.']);
    $this->assertDatabaseCount('cancellation_requests', 0);
    Mail::assertNothingSent();

    PublicForms::submit('kuendigen', [...$payload, 'effective_date' => '2026-11-03'])
        ->assertHasNoErrors();
});

it('renders Berlin time and the complete cancellation content with a separate operator greeting', function () {
    $this->travelTo(Carbon::parse('2026-11-03 13:07:00', 'UTC'));
    PublicForms::submit('kuendigen', [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'username' => '@person',
        'contract' => 'Oasis-Mitgliedschaft', 'type' => 'ausserordentlich', 'reason' => 'Important reason',
        'timing' => 'datum', 'effective_date' => '2026-11-04',
    ])->assertHasNoErrors();

    Mail::assertSent(CancellationReceipt::class, function ($mail) {
        if (! $mail->hasTo('person@example.tld')) {
            return false;
        }
        expect($mail->render())->toContain('03.11.2026', '14:07:00 CET', 'Test Person', '@person',
            'Oasis-Mitgliedschaft', 'Außerordentlich', 'Important reason', '04.11.2026');

        return true;
    });
    Mail::assertSent(CancellationReceipt::class, function ($mail) {
        if (! $mail->hasTo('operator@example.tld')) {
            return false;
        }
        expect($mail->envelope()->subject)->toBe('Kopie: Kündigung von Test Person');
        expect($mail->render())->toContain('Kopie: Kündigung von Test Person')->not->toContain('Bestätigung deiner Kündigung');

        return true;
    });
});

it('addresses the operator copy of a withdrawal to the operator', function () {
    PublicForms::submit('widerrufen', [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
    ])->assertHasNoErrors();

    Mail::assertSent(WithdrawalReceipt::class, function ($mail) {
        if (! $mail->hasTo('operator@example.tld')) {
            return false;
        }
        expect($mail->render())->toContain('Kopie: Widerruf von Test Person')->not->toContain('Eingangsbestätigung deines Widerrufs');

        return true;
    });
});

it('records good-faith consent and sends a moderation notice requesting review', function () {
    PublicForms::submit('melden', [
        'url' => 'https://example.tld/post', 'reason' => 'Illegal content', 'name' => 'Test Person',
        'email' => 'person@example.tld', 'truthful' => '1',
    ])->assertHasNoErrors();

    expect(ContentReport::sole()->truthful_confirmed_at)->not->toBeNull();
    Mail::assertSent(ContentReportNotice::class, function ($mail) {
        expect($mail->render())->toContain('Neue Inhaltsmeldung von Test Person', 'Bitte prüft')
            ->not->toContain('Eingangsbestätigung deiner Meldung');

        return $mail->hasTo('moderation@example.tld');
    });
});

it('uses the Berlin calendar day for cancellation dates just after midnight', function ($instant, $yesterday, $today) {
    $this->travelTo(Carbon::parse($instant, 'UTC'));
    $payload = [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich', 'timing' => 'datum',
    ];

    PublicForms::submit('kuendigen', [...$payload, 'effective_date' => $yesterday])
        ->assertHasErrors(['form.effective_date' => 'Bitte wähle heute oder ein Datum in der Zukunft.']);
    $this->assertDatabaseCount('cancellation_requests', 0);
    Mail::assertNothingSent();

    PublicForms::submit('kuendigen', [...$payload, 'effective_date' => $today])
        ->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.kuendigen'));
    $this->assertDatabaseHas('cancellation_requests', ['effective_date' => $today]);
})->with([
    'winter 00:30 Berlin' => ['2026-11-03 23:30:00', '2026-11-03', '2026-11-04'],
    'summer 00:30 Berlin' => ['2026-07-03 22:30:00', '2026-07-03', '2026-07-04'],
]);

it('shows the complete cancellation declaration with Berlin time after submission', function () {
    $this->travelTo(Carbon::parse('2026-11-03 13:07:00', 'UTC'));
    PublicForms::submit('kuendigen', [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'username' => '@person',
        'contract' => 'Oasis-Mitgliedschaft', 'type' => 'ausserordentlich',
        'reason' => 'Important reason <script>alert(1)</script>',
        'timing' => 'datum', 'effective_date' => '2026-11-04',
    ])->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.kuendigen'));

    $this->get(route('oasis.bestaetigung.kuendigen'))->assertOk()
        ->assertSee('03.11.2026 14:07:00 CET')
        ->assertSee('Test Person')->assertSee('person@example.tld')->assertSee('@person')
        ->assertSee('Oasis-Mitgliedschaft')->assertSee('Außerordentlich')
        ->assertSee('Important reason <script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('Zu einem bestimmten Datum')->assertSee('04.11.2026')
        ->assertSee('Du kannst diese Seite speichern oder drucken.');
});

it('shows the withdrawal declaration with Berlin time after submission', function () {
    $this->travelTo(Carbon::parse('2026-11-03 13:07:00', 'UTC'));
    PublicForms::submit('widerrufen', [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'contracted_on' => '2026-10-01',
    ])->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.widerrufen'));

    $this->get(route('oasis.bestaetigung.widerrufen'))->assertOk()
        ->assertSee('03.11.2026 14:07:00 CET')
        ->assertSee('Test Person')->assertSee('person@example.tld')
        ->assertSee('Oasis-Mitgliedschaft')->assertSee('01.10.2026')
        ->assertSee('Du kannst diese Seite speichern oder drucken.');
});
