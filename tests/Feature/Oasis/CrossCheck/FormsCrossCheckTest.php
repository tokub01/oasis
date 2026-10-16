<?php

/*
 * Independent checks of the Oasis forms, written against docs/oasis-design/PROMPT.md
 * (not against the implementation). Kept separate from the implementer's own tests.
 */

use App\Mail\Oasis\CancellationReceipt;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\ContentReportReceipt;
use App\Mail\Oasis\OrganisationNotice;
use App\Mail\Oasis\WaitlistConfirmation;
use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\CancellationRequest;
use App\Models\ContentReport;
use App\Models\OrganisationRequest;
use App\Models\WaitlistEntry;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\PublicForms;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    config([
        'oasis.mail.operator' => 'betreiber@example.tld',
        'oasis.mail.moderation' => 'moderation@example.tld',
    ]);
});

function cancellationPayload(array $overrides = []): array
{
    return [
        'name' => 'Lena Berger',
        'email' => 'lena@example.tld',
        'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich',
        'timing' => 'naechst',
        ...$overrides,
    ];
}

describe('Startliste (double opt-in)', function () {
    it('stores the entry unconfirmed with consent time and sends the opt-in mail', function () {
        PublicForms::submit('gruendung', ['email' => 'mira@example.tld', 'consent' => '1'])
            ->assertRedirect(route('oasis.bestaetigung.gruendung'));

        $entry = WaitlistEntry::sole();
        expect($entry->email)->toBe('mira@example.tld')
            ->and($entry->confirmed_at)->toBeNull()
            ->and($entry->consented_at)->not->toBeNull();

        Mail::assertSent(WaitlistConfirmation::class, fn ($mail) => $mail->hasTo('mira@example.tld'));
    });

    it('requires consent', function () {
        PublicForms::submit('gruendung', ['email' => 'mira@example.tld'])
            ->assertHasErrors('form.consent');

        expect(WaitlistEntry::count())->toBe(0);
        Mail::assertNothingSent();
    });

    it('puts a confirmation link and an unsubscribe link into the mail', function () {
        PublicForms::submit('gruendung', ['email' => 'mira@example.tld', 'consent' => '1']);

        Mail::assertSent(WaitlistConfirmation::class, function ($mail) {
            $html = $mail->render();

            return str_contains($html, '/startliste/bestaetigen/') && str_contains($html, '/startliste/abmelden/');
        });
    });

    it('confirms only through a valid signed link', function () {
        $entry = WaitlistEntry::create(['email' => 'mira@example.tld', 'consented_at' => now(), 'token' => str_repeat('a', 64)]);

        $this->get(route('oasis.waitlist.confirm', ['token' => $entry->token]))->assertForbidden();
        expect($entry->fresh()->confirmed_at)->toBeNull();

        $this->get(URL::signedRoute('oasis.waitlist.confirm', ['token' => $entry->token]))->assertOk();
        expect($entry->fresh()->confirmed_at)->not->toBeNull();
    });

    it('rejects an expired confirmation link', function () {
        $entry = WaitlistEntry::create(['email' => 'mira@example.tld', 'consented_at' => now(), 'token' => str_repeat('b', 64)]);
        $url = URL::temporarySignedRoute('oasis.waitlist.confirm', now()->subMinute(), ['token' => $entry->token]);

        $this->get($url)->assertForbidden();
    });
});

describe('Kündigen (§ 312k BGB)', function () {
    it('is reachable without login', function () {
        $this->get(route('oasis.kuendigen'))->assertOk()->assertSee('Jetzt kündigen');
    });

    it('stores the cancellation and confirms it immediately to the person and the operator', function () {
        PublicForms::submit('kuendigen', cancellationPayload())
            ->assertRedirect(route('oasis.bestaetigung.kuendigen'));

        expect(CancellationRequest::count())->toBe(1);
        Mail::assertSent(CancellationReceipt::class, fn ($mail) => $mail->hasTo('lena@example.tld'));
        Mail::assertSent(CancellationReceipt::class, fn ($mail) => $mail->hasTo('betreiber@example.tld'));
    });

    it('puts date, time and content into the confirmation', function () {
        // The person reads German local time, regardless of the app timezone.
        $this->travelTo(Carbon::create(2026, 11, 3, 14, 7, 0, 'Europe/Berlin'));

        PublicForms::submit('kuendigen', cancellationPayload());

        Mail::assertSent(CancellationReceipt::class, function ($mail) {
            $html = $mail->render();

            return $mail->hasTo('lena@example.tld')
                && str_contains($html, '03.11.2026')
                && str_contains($html, '14:07')
                && str_contains($html, 'Lena Berger')
                && str_contains($html, 'Oasis-Mitgliedschaft');
        });
    });

    it('requires a reason for an extraordinary cancellation', function () {
        PublicForms::submit('kuendigen', cancellationPayload(['type' => 'ausserordentlich']))
            ->assertHasErrors('form.reason');

        expect(CancellationRequest::count())->toBe(0);
    });

    it('requires a date when cancelling at a specific date', function () {
        PublicForms::submit('kuendigen', cancellationPayload(['timing' => 'datum']))
            ->assertHasErrors('form.effective_date');
    });

    it('rejects a cancellation date in the past', function () {
        PublicForms::submit('kuendigen', cancellationPayload([
            'timing' => 'datum',
            'effective_date' => now()->subDay()->format('Y-m-d'),
        ]))->assertHasErrors('form.effective_date');
    });

    it('does not offer anything to keep the person on the cancellation page', function () {
        $this->get(route('oasis.kuendigen'))
            ->assertOk()
            ->assertDontSee('Rabatt')
            ->assertDontSee('Bleib doch');
    });
});

describe('Widerrufen', function () {
    it('has its own page with the exact button label', function () {
        $this->get(route('oasis.widerrufen'))->assertOk()->assertSee('Widerruf bestätigen');
    });

    it('stores the withdrawal and sends the receipt to the person and the operator', function () {
        PublicForms::submit('widerrufen', [
            'name' => 'Lena Berger',
            'email' => 'lena@example.tld',
            'contract' => 'Oasis-Mitgliedschaft',
        ])->assertRedirect(route('oasis.bestaetigung.widerrufen'));

        expect(WithdrawalRequest::count())->toBe(1);
        Mail::assertSent(WithdrawalReceipt::class, fn ($mail) => $mail->hasTo('lena@example.tld'));
        Mail::assertSent(WithdrawalReceipt::class, fn ($mail) => $mail->hasTo('betreiber@example.tld'));
    });
});

describe('Inhalte melden (DSA Art. 16)', function () {
    it('works without an account and notifies the reporter and moderation', function () {
        PublicForms::submit('melden', [
            'url' => 'https://get-oasis.tld/profil/mira',
            'reason' => 'Beleidigung',
            'name' => 'Lena Berger',
            'email' => 'lena@example.tld',
            'truthful' => '1',
        ])->assertRedirect(route('oasis.bestaetigung.melden'));

        expect(ContentReport::count())->toBe(1);
        Mail::assertSent(ContentReportReceipt::class, fn ($mail) => $mail->hasTo('lena@example.tld'));
        Mail::assertSent(ContentReportNotice::class, fn ($mail) => $mail->hasTo('moderation@example.tld'));
    });

    it('requires the good-faith confirmation', function () {
        PublicForms::submit('melden', [
            'url' => 'https://get-oasis.tld/profil/mira',
            'reason' => 'Beleidigung',
            'name' => 'Lena Berger',
            'email' => 'lena@example.tld',
        ])->assertHasErrors('form.truthful');
    });
});

describe('Anfrage für Organisationen', function () {
    it('stores the request and mails the operator', function () {
        PublicForms::submit('gemeinsam', [
            'organisation' => 'Fachschaft Biologie',
            'people' => 40,
            'email' => 'fs@example.tld',
        ])->assertRedirect(route('oasis.bestaetigung.gemeinsam'));

        expect(OrganisationRequest::count())->toBe(1);
        Mail::assertSent(OrganisationNotice::class, fn ($mail) => $mail->hasTo('betreiber@example.tld'));
    });
});

describe('All forms', function () {
    it('rejects a filled honeypot without storing or mailing', function (string $page, array $payload) {
        PublicForms::submit($page, [...$payload, 'website' => 'https://spam.tld'])->assertHasErrors('form.website');

        Mail::assertNothingSent();
        expect(WaitlistEntry::count() + CancellationRequest::count() + WithdrawalRequest::count()
            + ContentReport::count() + OrganisationRequest::count())->toBe(0);
    })->with([
        'startliste' => ['gruendung', ['email' => 'a@example.tld', 'consent' => '1']],
        'kuendigen' => ['kuendigen', ['name' => 'A', 'email' => 'a@example.tld', 'contract' => 'Oasis-Mitgliedschaft', 'type' => 'ordentlich', 'timing' => 'naechst']],
        'widerrufen' => ['widerrufen', ['name' => 'A', 'email' => 'a@example.tld', 'contract' => 'Oasis-Mitgliedschaft']],
        'melden' => ['melden', ['url' => 'https://a.tld', 'reason' => 'x', 'name' => 'A', 'email' => 'a@example.tld', 'truthful' => '1']],
        'organisation' => ['gemeinsam', ['organisation' => 'A', 'people' => 5, 'email' => 'a@example.tld']],
    ]);

    it('shows German validation messages', function () {
        PublicForms::submit('kuendigen', [])
            ->assertHasErrors(['form.name' => 'Bitte fülle dieses Feld aus.']);
    });

    it('does not show a confirmation page without a submission', function (string $route) {
        $this->get(route($route))->assertNotFound();
    })->with([
        'oasis.bestaetigung.gruendung',
        'oasis.bestaetigung.kuendigen',
        'oasis.bestaetigung.widerrufen',
        'oasis.bestaetigung.melden',
        'oasis.bestaetigung.gemeinsam',
    ]);

    it('does not let one form use up the limit of another', function () {
        foreach (range(1, 7) as $i) {
            PublicForms::submit('gruendung', []);
        }

        PublicForms::submit('kuendigen', cancellationPayload())
            ->assertRedirect(route('oasis.bestaetigung.kuendigen'));
    });

    it('is rate limited', function () {
        foreach (range(1, 6) as $i) {
            PublicForms::submit('widerrufen', []);
        }

        PublicForms::submit('widerrufen', [])->assertHasErrors('throttle');
    });
});
