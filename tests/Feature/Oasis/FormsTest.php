<?php

/*
 * Exercises public Oasis forms: validation, storage, German feedback, mail recipients and signed double opt-in.
 * Covers § 312k BGB cancellation receipts, §§ 355 ff. BGB withdrawals and DSA Art. 16 notices.
 */

use App\Mail\Oasis\CancellationReceipt;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\ContentReportReceipt;
use App\Mail\Oasis\OrganisationNotice;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\CancellationRequest;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Mail::fake();
    config([
        'oasis.mail.operator' => 'operator@example.tld',
        'oasis.mail.moderation' => 'moderation@example.tld',
    ]);
    $this->withoutVite();
});

dataset('oasis forms', [
    'waitlist' => ['gruendung', 'waitlist_entries', [
        'email' => 'person@example.tld', 'group' => 'My group', 'consent' => '1',
    ], ['email', 'consent'], WaitlistConfirmation::class, 1],
    'cancellation' => ['kuendigen', 'cancellation_requests', [
        'name' => 'Test Person', 'email' => 'person@example.tld',
        'contract' => 'Oasis-Mitgliedschaft', 'type' => 'ordentlich', 'timing' => 'naechst',
    ], ['name', 'email', 'contract', 'type', 'timing'], CancellationReceipt::class, 2],
    'withdrawal' => ['widerrufen', 'withdrawal_requests', [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
    ], ['name', 'email', 'contract'], WithdrawalReceipt::class, 2],
    'report' => ['melden', 'content_reports', [
        'url' => 'https://example.tld/posts/1', 'reason' => 'Illegal content',
        'name' => 'Test Person', 'email' => 'person@example.tld', 'truthful' => '1',
    ], ['url', 'reason', 'name', 'email', 'truthful'], ContentReportReceipt::class, 2],
    'organisation' => ['gemeinsam', 'organisation_requests', [
        'organisation' => 'Our association', 'people' => 40, 'email' => 'person@example.tld',
    ], ['organisation', 'people', 'email'], OrganisationNotice::class, 1],
]);

it('stores a valid submission and sends the required mail', function ($page, $table, $data, $required, $mail, $count) {
    $this->post(route('oasis.'.$page.'.store'), $data)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('oasis.bestaetigung.'.$page));

    $this->assertDatabaseCount($table, 1);
    $this->assertDatabaseHas($table, ['email' => 'person@example.tld']);
    Mail::assertSentCount($count);
    Mail::assertSent($mail, fn ($message) => $message->hasTo(
        $page === 'gemeinsam' ? 'operator@example.tld' : 'person@example.tld'
    ));
    if (in_array($page, ['kuendigen', 'widerrufen'])) {
        Mail::assertSent($mail, fn ($message) => $message->hasTo('operator@example.tld'));
    }
    if ($page === 'melden') {
        Mail::assertSent(ContentReportNotice::class, fn ($message) => $message->hasTo('moderation@example.tld'));
        $this->assertDatabaseHas($table, ['reason' => $data['reason']]);
    }
    if ($page === 'gruendung') {
        expect(WaitlistEntry::first()->consented_at)->not->toBeNull();
        expect(WaitlistEntry::first()->confirmed_at)->toBeNull();
    }
    $this->get(route('oasis.bestaetigung.'.$page))->assertOk();
})->with('oasis forms');

it('rejects missing required fields without storage or mail', function ($page, $table, $data, $required) {
    $this->post(route('oasis.'.$page.'.store'), [])->assertSessionHasErrors($required);
    $this->assertDatabaseCount($table, 0);
    Mail::assertNothingSent();
})->with('oasis forms');

it('rejects invalid email addresses with a German error', function ($page, $table, $data) {
    $this->post(route('oasis.'.$page.'.store'), [...$data, 'email' => 'invalid'])
        ->assertSessionHasErrors(['email' => 'Bitte gib eine gültige E-Mail-Adresse ein.']);
    $this->assertDatabaseCount($table, 0);
    Mail::assertNothingSent();
})->with('oasis forms');

it('rejects the honeypot without storing anything or sending mail', function ($page, $table, $data) {
    $this->post(route('oasis.'.$page.'.store'), [...$data, 'website' => 'https://spam.tld'])
        ->assertSessionHasErrors('website');
    $this->assertDatabaseCount($table, 0);
    Mail::assertNothingSent();
})->with('oasis forms');

it('throttles submissions', function ($page, $table) {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('oasis.'.$page.'.store'), [])->assertSessionHasErrors();
    }
    $this->post(route('oasis.'.$page.'.store'), [])->assertStatus(429);
    $this->assertDatabaseCount($table, 0);
    Mail::assertNothingSent();
})->with('oasis forms');

it('does not display a submission confirmation before a submission', function ($page) {
    $this->get(route('oasis.bestaetigung.'.$page))->assertNotFound();
})->with('oasis forms');

it('requires the extraordinary reason and the requested cancellation date', function () {
    $this->post(route('oasis.kuendigen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ausserordentlich', 'timing' => 'datum',
    ])->assertSessionHasErrors(['reason', 'effective_date']);
    $this->assertDatabaseCount('cancellation_requests', 0);
    Mail::assertNothingSent();
});

it('stores extraordinary cancellation content and renders its timestamp in the receipt', function () {
    $this->freezeTime();
    $this->post(route('oasis.kuendigen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ausserordentlich', 'reason' => 'Important reason', 'timing' => 'datum',
        'effective_date' => now()->addMonth()->format('Y-m-d'), 'username' => '@person',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('cancellation_requests', ['reason' => 'Important reason']);
    expect(CancellationRequest::sole()->effective_date->format('Y-m-d'))->toBe(now()->addMonth()->format('Y-m-d'));
    $html = (new CancellationReceipt(CancellationRequest::first()))->render();
    expect($html)->toContain('Important reason', now()->addMonth()->format('d.m.Y'), 'Oasis-Mitgliedschaft',
        now()->timezone('Europe/Berlin')->format('d.m.Y'),
        now()->timezone('Europe/Berlin')->format('H:i:s T'));
});

it('ignores inapplicable cancellation fields', function () {
    $this->post(route('oasis.kuendigen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich', 'timing' => 'naechst', 'reason' => 'Not applicable', 'effective_date' => 'invalid',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('cancellation_requests', ['reason' => null, 'effective_date' => null]);
});

it('rejects invalid cancellation selections and dates', function () {
    $this->post(route('oasis.kuendigen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Other contract',
        'type' => 'invalid', 'timing' => 'datum', 'effective_date' => 'not-a-date',
    ])->assertSessionHasErrors(['contract', 'type', 'effective_date']);
    Mail::assertNothingSent();
});

it('accepts an optional withdrawal date and renders the receipt', function () {
    $this->post(route('oasis.widerrufen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'contracted_on' => '2026-10-01',
    ])->assertSessionHasNoErrors();
    Mail::assertSent(WithdrawalReceipt::class, function ($mail) {
        expect($mail->render())->toContain('01.10.2026', 'Oasis-Mitgliedschaft');

        return true;
    });
});

it('rejects a malformed optional withdrawal date', function () {
    $this->post(route('oasis.widerrufen.store'), [
        'name' => 'Test Person', 'email' => 'person@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'contracted_on' => 'not-a-date',
    ])->assertSessionHasErrors('contracted_on');
    $this->assertDatabaseCount('withdrawal_requests', 0);
    Mail::assertNothingSent();
});

it('rejects non-http report links and missing declarations', function () {
    $this->post(route('oasis.melden.store'), [
        'url' => 'ftp://example.tld/post', 'reason' => 'Illegal content', 'name' => 'Person',
        'email' => 'person@example.tld', 'truthful' => '0',
    ])->assertSessionHasErrors(['url', 'truthful']);
    $this->assertDatabaseCount('content_reports', 0);
    Mail::assertNothingSent();
});

it('requires at least two whole people for an organisation', function () {
    $this->post(route('oasis.gemeinsam.store'), [
        'organisation' => 'Association', 'people' => 1.5, 'email' => 'person@example.tld',
    ])->assertSessionHasErrors('people');
    $this->assertDatabaseCount('organisation_requests', 0);
    Mail::assertNothingSent();
});

it('normalizes waitlist email and resends unconfirmed opt-in without revealing duplicates', function () {
    $data = ['email' => 'PERSON@example.tld', 'consent' => '1'];
    $this->post(route('oasis.gruendung.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('oasis.gruendung.store'), $data)->assertSessionHasNoErrors()
        ->assertRedirect(route('oasis.bestaetigung.gruendung'));
    $this->assertDatabaseCount('waitlist_entries', 1);
    $this->assertDatabaseHas('waitlist_entries', ['email' => 'person@example.tld']);
    Mail::assertSentCount(2);
});

it('sends signed confirmation and unsubscribe URLs in the waitlist mail', function () {
    $this->post(route('oasis.gruendung.store'), ['email' => 'person@example.tld', 'consent' => '1']);
    Mail::assertSent(WaitlistConfirmation::class, function ($mail) {
        $urls = $mail->content()->with;
        expect($urls['confirmUrl'])->toContain('signature=', 'expires=');
        expect($urls['unsubscribeUrl'])->toContain('signature=');
        expect($mail->render())->toContain('E-Mail-Adresse bestätigen', 'Von der Startliste abmelden');
        $this->get($urls['confirmUrl'])->assertOk();
        $this->get($urls['unsubscribeUrl'])->assertOk();
        $this->assertDatabaseCount('waitlist_entries', 1);
        $this->post(URL::signedRoute('oasis.waitlist.destroy', ['token' => $mail->entry->token]))->assertOk();

        return true;
    });
    $this->assertDatabaseCount('waitlist_entries', 0);
});

it('confirms the waitlist only with a signed link and preserves the first timestamp', function () {
    $this->post(route('oasis.gruendung.store'), ['email' => 'person@example.tld', 'consent' => '1']);
    $entry = WaitlistEntry::first();
    $this->get(route('oasis.waitlist.confirm', ['token' => $entry->token]))->assertForbidden();
    expect($entry->fresh()->confirmed_at)->toBeNull();
    $url = URL::temporarySignedRoute('oasis.waitlist.confirm', now()->addDays(7), ['token' => $entry->token]);
    $this->get($url)->assertOk();
    $confirmedAt = $entry->fresh()->confirmed_at;
    $this->travel(1)->hours();
    $this->get($url)->assertOk();
    expect($entry->fresh()->confirmed_at->equalTo($confirmedAt))->toBeTrue();
    Mail::assertSentCount(1);
});

it('rejects expired and tampered waitlist confirmation links', function () {
    $this->post(route('oasis.gruendung.store'), ['email' => 'person@example.tld', 'consent' => '1']);
    $entry = WaitlistEntry::first();
    $this->get(URL::temporarySignedRoute('oasis.waitlist.confirm', now()->subMinute(), ['token' => $entry->token]))->assertForbidden();
    $url = URL::temporarySignedRoute('oasis.waitlist.confirm', now()->addDays(7), ['token' => $entry->token]);
    $this->get(str_replace($entry->token, str_repeat('a', 64), $url))->assertForbidden();
    expect($entry->fresh()->confirmed_at)->toBeNull();
});

it('requires a signed POST to unsubscribe and leaves scanner GET requests harmless', function () {
    $this->post(route('oasis.gruendung.store'), ['email' => 'person@example.tld', 'consent' => '1']);
    $entry = WaitlistEntry::first();
    $this->get(route('oasis.waitlist.unsubscribe', ['token' => $entry->token]))->assertForbidden();
    $this->assertDatabaseCount('waitlist_entries', 1);
    $url = URL::signedRoute('oasis.waitlist.unsubscribe', ['token' => $entry->token]);
    $this->get($url)->assertOk();
    $this->get($url)->assertOk();
    $this->assertDatabaseCount('waitlist_entries', 1);
    $this->post(route('oasis.waitlist.destroy', ['token' => $entry->token]))->assertForbidden();
    $this->assertDatabaseCount('waitlist_entries', 1);
    $postUrl = URL::signedRoute('oasis.waitlist.destroy', ['token' => $entry->token]);
    $this->post($postUrl)->assertOk();
    $this->post($postUrl)->assertOk();
    $this->assertDatabaseCount('waitlist_entries', 0);
    Mail::assertSentCount(1);
});

it('renders all assigned pages publicly with one heading and real form actions', function ($page) {
    $response = $this->get(route('oasis.'.$page))->assertOk();
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
    $response->assertSee(route('oasis.'.$page.'.store'), false)->assertSee('name="_token"', false);
})->with(['gruendung', 'gemeinsam', 'kuendigen', 'widerrufen', 'melden']);

it('renders separate legal documents and their active navigation', function ($page) {
    $response = $this->get(route('oasis.'.$page))->assertOk();
    $response->assertSee('aria-current="page"', false);
    if ($page !== 'melden') {
        // Every legal text is still a draft and must say so until a lawyer has reviewed it.
        expect($response->getContent())->toMatch('/anwaltlich (geprüften Text|prüfen lassen)/');
    }
})->with(['impressum', 'datenschutz', 'agb', 'widerrufsbelehrung', 'regeln', 'melden']);

it('does not show success when sending mail fails', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('oasis.gruendung.store'), [
        'email' => 'person@example.tld', 'consent' => '1',
    ]))->toThrow(RuntimeException::class, 'Mail unavailable');

    $this->assertDatabaseCount('waitlist_entries', 1);
    expect(session('oasis.submitted.gruendung'))->toBeNull();
});

it('does not unlock unrelated success pages', function () {
    $this->post(route('oasis.gruendung.store'), ['email' => 'person@example.tld', 'consent' => '1']);
    $this->get(route('oasis.bestaetigung.kuendigen'))->assertNotFound();
});
