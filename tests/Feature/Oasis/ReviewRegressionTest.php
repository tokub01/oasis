<?php

use App\Mail\Oasis\CancellationReceipt;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

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
    $attributes = $entry->getAttributes();

    $this->post(route('oasis.gruendung.store'), [
        'email' => 'MEMBER@example.tld', 'consent' => '1', 'group' => 'Another group',
    ])->assertSessionHasNoErrors()->assertRedirect(route('oasis.bestaetigung.gruendung'));

    expect($entry->fresh()->getAttributes())->toBe($attributes);
    Mail::assertNothingSent();
    $this->get(route('oasis.bestaetigung.gruendung'))->assertOk()->assertDontSee('Wir haben dir einen Bestätigungslink geschickt');
});

it('resends an unconfirmed opt-in without changing its original consent or token', function () {
    $entry = WaitlistEntry::create([
        'email' => 'member@example.tld', 'consented_at' => now()->subDays(2), 'token' => str_repeat('d', 64),
    ]);
    $attributes = $entry->getAttributes();

    $this->post(route('oasis.gruendung.store'), ['email' => $entry->email, 'consent' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect(route('oasis.bestaetigung.gruendung'));

    expect($entry->fresh()->getAttributes())->toBe($attributes);
    Mail::assertSent(WaitlistConfirmation::class, 1);
});

it('rejects past cancellation dates with a German error and accepts today', function () {
    $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));
    $payload = [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich', 'timing' => 'datum',
    ];

    $this->post(route('oasis.kuendigen.store'), [...$payload, 'effective_date' => '2026-11-02'])
        ->assertSessionHasErrors(['effective_date' => 'Bitte wähle heute oder ein Datum in der Zukunft.']);
    $this->assertDatabaseCount('cancellation_requests', 0);
    Mail::assertNothingSent();

    $this->post(route('oasis.kuendigen.store'), [...$payload, 'effective_date' => '2026-11-03'])
        ->assertSessionHasNoErrors();
});

it('renders Berlin time and the complete cancellation content with a separate operator greeting', function () {
    $this->travelTo(Carbon::parse('2026-11-03 13:07:00', 'UTC'));
    $this->post(route('oasis.kuendigen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'username' => '@person',
        'contract' => 'Oasis-Mitgliedschaft', 'type' => 'ausserordentlich', 'reason' => 'Important reason',
        'timing' => 'datum', 'effective_date' => '2026-11-04',
    ])->assertSessionHasNoErrors();

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
    $this->post(route('oasis.widerrufen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
    ])->assertSessionHasNoErrors();

    Mail::assertSent(WithdrawalReceipt::class, function ($mail) {
        if (! $mail->hasTo('operator@example.tld')) {
            return false;
        }
        expect($mail->render())->toContain('Kopie: Widerruf von Test Person')->not->toContain('Eingangsbestätigung deines Widerrufs');

        return true;
    });
});

it('records good-faith consent and sends a moderation notice requesting review', function () {
    $this->post(route('oasis.melden.store'), [
        'url' => 'https://example.tld/post', 'reason' => 'Illegal content', 'name' => 'Test Person',
        'email' => 'person@example.tld', 'truthful' => '1',
    ])->assertSessionHasNoErrors();

    expect(\App\Models\ContentReport::sole()->truthful_confirmed_at)->not->toBeNull();
    Mail::assertSent(ContentReportNotice::class, function ($mail) {
        expect($mail->render())->toContain('Neue Inhaltsmeldung von Test Person', 'Bitte prüft')
            ->not->toContain('Eingangsbestätigung deiner Meldung');

        return $mail->hasTo('moderation@example.tld');
    });
});
