<?php

/* Melden für Mitglieder (App\Livewire\Safety\QuickReport): a few taps, no legal form, reassuring, with help contacts. */

use App\Livewire\Safety\QuickReport;
use App\Mail\Oasis\ContentReportNotice;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

it('reports a Tropfen with a category and an optional comment, filled from the account', function () {
    $reporter = N::member();
    $post = N::post(N::member());
    $url = route('network.posts.show', $post);

    Livewire::actingAs($reporter)->withQueryParams(['url' => $url])->test(QuickReport::class)
        ->assertSee('Melden ist ausdrücklich erwünscht')->assertSee('erfährt nicht, wer gemeldet hat')
        ->call('send')->assertHasErrors('category')
        ->set('category', 'mobbing')->call('send')->assertHasNoErrors()->assertSet('sent', true)
        ->assertSee('Danke, dass du das meldest')->assertSee('116 111')->assertSee('juuuport.de');

    $report = ContentReport::sole();
    expect($report->url)->toBe($url)->and($report->email)->toBe($reporter->email)
        ->and($report->reason)->toContain('Mobbing')
        ->and(ModerationCase::count())->toBe(1);
    Mail::assertSent(ContentReportNotice::class);
});

it('accepts only links to Oasis itself', function () {
    Livewire::actingAs(N::member())->withQueryParams(['url' => 'https://evil.tld/x'])->test(QuickReport::class)->assertNotFound();
});

it('sends members to the quick form and guests to the formal one', function () {
    $post = N::post(N::member(), ['visibility' => 'public']);

    $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertSee(route('app.report'), false);
    auth()->logout();
    $this->get(route('network.posts.show', $post))->assertDontSee(route('app.report'), false);
});
