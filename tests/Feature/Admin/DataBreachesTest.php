<?php

/* Register of data breaches (Art. 33/34 DSGVO): documentation, 72-hour deadline, drafted report, no automatic sending. */

use App\Enums\Role;
use App\Livewire\Admin\DataBreaches;
use App\Mail\Privacy\DataBreachAlert;
use App\Models\DataBreach;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

function breachAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('second-factor'), 'two_factor_confirmed_at' => now()]);
}

/** @return array<string, mixed> */
function breachForm(array $overrides = []): array
{
    return $overrides + ['title' => 'Rechnungs-E-Mails an falsche Empfänger', 'discovered_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
        'description' => 'Ein Fehler im Versand schickte Rechnungen an andere Mitglieder.', 'data_categories' => 'Name, E-Mail-Adresse, Betrag',
        'affected_people' => '12', 'risk' => 'risk', 'consequences' => 'Andere Mitglieder kennen Name und Beitrag.',
        'measures' => 'Versand gestoppt, Fehler behoben, Empfänger um Löschung gebeten.'];
}

it('is open to admins with 2FA only', function () {
    $this->actingAs(N::member())->get(route('admin.breaches'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]))
        ->get(route('admin.breaches'))->assertForbidden();
    $this->actingAs(breachAdmin())->get(route('admin.breaches'))->assertOk()->assertSee('Datenpannen')->assertSee('Noch keine Datenpannen erfasst.');
});

it('records a breach with risk, shows the deadline and a drafted Art. 33 report, and alerts the operator without details', function () {
    $admin = breachAdmin();

    Livewire::actingAs($admin)->test(DataBreaches::class)->set('form', breachForm())->call('record')->assertHasNoErrors()
        ->assertSee('Meldung an die Aufsichtsbehörde fällig in 69 Stunden')
        ->assertSee('Meldung einer Verletzung des Schutzes personenbezogener Daten nach Art. 33 DSGVO')
        ->assertSee('Ungefähre Zahl betroffener Personen: 12')->assertSee(config('oasis.privacy.authority'));

    $breach = DataBreach::sole();
    expect($breach->reportOutstanding())->toBeTrue()->and($breach->log[0]['text'])->toBe('Datenpanne erfasst.');
    Mail::assertSent(DataBreachAlert::class, fn (DataBreachAlert $mail) => $mail->hasTo(config('oasis.mail.operator'))
        && ! str_contains($mail->render(), 'falsche Empfänger an andere') && ! str_contains($mail->render(), 'Name, E-Mail-Adresse, Betrag'));
});

it('documents a breach without risk without any report or alert', function () {
    Livewire::actingAs(breachAdmin())->test(DataBreaches::class)->set('form', breachForm(['risk' => 'none']))->call('record')
        ->assertSee('Eine Meldung ist bei dieser Einstufung nicht nötig.')->assertDontSee('Vorbereitete Meldung');
    Mail::assertNothingSent();
});

it('refuses missing facts and a discovery in the future', function () {
    Livewire::actingAs(breachAdmin())->test(DataBreaches::class)
        ->set('form', breachForm(['title' => '', 'risk' => 'egal', 'discovered_at' => now()->addDay()->format('Y-m-d\TH:i')]))->call('record')
        ->assertHasErrors(['form.title', 'form.risk', 'form.discovered_at']);
    expect(DataBreach::count())->toBe(0);
});

it('warns after 72 hours, records report and notice by hand, and closes only when the duties are done', function () {
    $admin = breachAdmin();
    $page = Livewire::actingAs($admin)->test(DataBreaches::class)->set('form', breachForm(['risk' => 'high']))->call('record');
    $id = DataBreach::sole()->id;

    $this->travel(3)->days();
    $page = Livewire::actingAs($admin)->test(DataBreaches::class)->assertSee('Die 72 Stunden sind abgelaufen')->assertSee('[bitte ergänzen]');
    $page->call('close', $id)->assertHasErrors('close.'.$id);
    $page->set('references.'.$id, 'x')->call('recordReport', $id)->assertHasErrors('references.'.$id);
    $page->set('references.'.$id, 'LDI NRW, Az. 7/26')->call('recordReport', $id)->assertHasNoErrors()->assertSee('LDI NRW, Az. 7/26');
    $page->call('close', $id)->assertHasErrors('close.'.$id);
    $page->call('recordNotified', $id)->set('notes.'.$id, 'Alle Betroffenen per E-Mail informiert.')->call('addNote', $id)
        ->call('close', $id)->assertHasNoErrors()->assertSee('Abgeschlossen');

    $breach = DataBreach::sole();
    expect($breach->closed_at)->not->toBeNull()->and($breach->authority_reported_at)->not->toBeNull()
        ->and(collect($breach->log)->pluck('text')->implode(' '))->toContain('Nach Ablauf der 72 Stunden', 'Alle Betroffenen per E-Mail informiert.', 'Abgeschlossen.');
    Livewire::actingAs($admin)->test(DataBreaches::class)->call('addNote', $id)->assertNotFound();
});

it('prunes closed entries after the retention period and keeps open ones', function () {
    config(['oasis.privacy.records_years' => 3]);
    $closed = DataBreach::create(breachForm(['discovered_at' => now()->subYears(4), 'affected_people' => 1]) + ['log' => []]);
    $closed->forceFill(['closed_at' => now()->subYears(3)->subDay()])->save();
    $open = DataBreach::create(breachForm(['discovered_at' => now()->subYears(4), 'affected_people' => 1]) + ['log' => []]);

    $this->artisan('model:prune', ['--model' => [DataBreach::class]])->assertSuccessful();

    $this->assertModelMissing($closed);
    $this->assertModelExists($open);
});
