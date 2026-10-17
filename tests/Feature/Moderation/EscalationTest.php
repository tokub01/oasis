<?php

/*
 * Escalation of moderation cases (docs/oasis-moderation.md, "Eskalation"): threats to life (Art. 18 DSA) and child
 * abuse. Evidence is secured apart from the content, quarantine hides images from everyone, Oasis sends nothing to any
 * authority, and every step lands in the case history.
 */

use App\Actions\Account\DeleteAccount;
use App\Actions\Moderation\EscalateCase;
use App\Actions\Network\DeletePost;
use App\Ai\Agents\ContentCheck as ContentCheckAgent;
use App\Enums\Moderation\Escalation;
use App\Enums\Moderation\EventType;
use App\Enums\Moderation\Priority;
use App\Enums\Role;
use App\Livewire\Moderation\CaseDetail;
use App\Livewire\Moderation\Queue;
use App\Mail\Moderation\EscalationAlert;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\ModerationEvidence;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Storage::fake(config('oasis.media_disk'));
});

function escalationStaff(Role $role = Role::Moderator): User
{
    return User::factory()->create(['role' => $role, 'two_factor_secret' => encrypt('second-factor'), 'two_factor_confirmed_at' => now()]);
}

/** A Tropfen with one stored image, reported through the public form (which opens the case). */
function reportedPost(): array
{
    $post = N::post(N::member(), ['body' => 'Gemeldeter Text']);
    Storage::disk(config('oasis.media_disk'))->put('network/posts/'.$post->id.'/1.webp', 'bilddaten');
    $post->images()->create(['path' => 'network/posts/'.$post->id.'/1.webp', 'alt' => 'Ein Bild', 'width' => 10, 'height' => 10, 'position' => 1]);
    $report = ContentReport::create(['url' => route('network.posts.show', $post), 'reason' => 'Bitte sofort prüfen.',
        'name' => 'Meldende Person', 'email' => 'meldung@example.tld', 'truthful_confirmed_at' => now()]);

    return [$post, ModerationCase::where('source_type', $report->getMorphClass())->where('source_id', $report->id)->sole()];
}

it('quarantines and secures a Tropfen escalated as child abuse, alerts the inbox without content, and only once', function () {
    [$post, $case] = reportedPost();
    $moderator = escalationStaff();

    Livewire::actingAs($moderator)->test(CaseDetail::class, ['case' => $case])
        ->call('escalate', 'child_abuse')->assertSee('Eskaliert: Missbrauchsdarstellung')->assertSee('Oasis meldet nichts selbst');

    $case->refresh();
    $post->refresh();
    expect($case->escalation)->toBe(Escalation::ChildAbuse)->and($case->priority)->toBe(Priority::Urgent)
        ->and($case->escalated_by)->toBe($moderator->id)
        ->and($post->held_at)->not->toBeNull()->and($post->quarantined_at)->not->toBeNull();
    $image = $case->evidence()->where('kind', 'image')->sole();
    expect($image->sha256)->toBe(hash('sha256', 'bilddaten'))
        ->and(Storage::disk(config('oasis.media_disk'))->get($image->path))->toBe('bilddaten')
        ->and($case->evidence()->where('kind', 'text')->sole()->content)->toBe('Gemeldeter Text')
        ->and($image->purge_after->isSameDay(now()->addDays(90)))->toBeTrue();

    foreach ([$post->author, $moderator] as $viewer) {
        $this->actingAs($viewer)->get(route('network.images.show', [$post, $post->images()->first()->id]))->assertNotFound();
    }
    Mail::assertSent(EscalationAlert::class, fn (EscalationAlert $mail) => $mail->hasTo(config('oasis.mail.moderation'))
        && ! str_contains($mail->render(), 'Gemeldeter Text') && ! str_contains($mail->render(), $post->author->username));

    app(EscalateCase::class)->handle($case, $moderator, Escalation::ThreatToLife);
    expect($case->fresh()->escalation)->toBe(Escalation::ChildAbuse)->and($case->evidence()->count())->toBe(2);
    Mail::assertSentCount(1);
});

it('escalates a threat to life without quarantine and records a report made by hand', function () {
    [$post, $case] = reportedPost();
    $moderator = escalationStaff();

    $page = Livewire::actingAs($moderator)->test(CaseDetail::class, ['case' => $case])->call('escalate', 'threat_to_life')
        ->assertSee('Polizei-Notruf 110')->assertSee('[noch festzulegen');
    expect($post->fresh()->quarantined_at)->toBeNull();

    $page->set('reference', 'x')->call('recordReport')->assertHasErrors('reference');
    $page->set('reference', 'BKA, Az. 123/26')->call('recordReport')->assertHasNoErrors()->assertSee('BKA, Az. 123/26');
    expect($case->fresh()->authority_reported_at)->not->toBeNull()
        ->and($case->events()->pluck('type')->all())->toContain(EventType::Escalated, EventType::AuthorityReported);

    Livewire::actingAs($moderator)->test(CaseDetail::class, ['case' => $case])->call('escalate', 'whatever')->assertStatus(422);
});

it('keeps evidence after the Tropfen and the account are deleted, and deletes it with its files after the period', function () {
    [$post, $case] = reportedPost();
    app(EscalateCase::class)->handle($case, escalationStaff(), Escalation::ThreatToLife);
    $image = $case->evidence()->where('kind', 'image')->sole();
    $author = $post->author;

    app(DeletePost::class)->handle($post);
    app(DeleteAccount::class)->handle($author);

    expect($case->evidence()->count())->toBe(2)
        ->and($case->evidence()->where('kind', 'text')->sole()->meta['account']['username'])->toBe($author->username);
    Storage::disk(config('oasis.media_disk'))->assertExists($image->path);

    $this->travel(91)->days();
    $this->artisan('model:prune', ['--model' => [ModerationEvidence::class]])->assertSuccessful();
    expect($case->evidence()->count())->toBe(0);
    Storage::disk(config('oasis.media_disk'))->assertMissing($image->path);
});

it('lets only admins download, extend, delete evidence and lift the quarantine', function () {
    [$post, $case] = reportedPost();
    $moderator = escalationStaff();
    app(EscalateCase::class)->handle($case, $moderator, Escalation::ChildAbuse);

    $this->actingAs($moderator)->get(route('moderation.cases.evidence', $case))->assertForbidden();
    foreach (['extendEvidence', 'deleteEvidence', 'liftQuarantine'] as $method) {
        Livewire::actingAs($moderator)->test(CaseDetail::class, ['case' => $case])->call($method)->assertForbidden();
    }

    $admin = escalationStaff(Role::Admin);
    $download = $this->actingAs($admin)->get(route('moderation.cases.evidence', $case))->assertOk()->assertDownload('oasis-fall-'.$case->id.'-beweise.zip');
    $zip = new ZipArchive;
    $zip->open($download->getFile()->getPathname());
    $manifest = json_decode($zip->getFromName('beweise.json'), true);
    expect($manifest['art'])->toBe('Missbrauchsdarstellung')->and(count($manifest['beweise']))->toBe(2)
        ->and($zip->getFromName('post-'.$post->id.'-1.webp'))->toBe('bilddaten');

    $this->travel(30)->days();
    Livewire::actingAs($admin)->test(CaseDetail::class, ['case' => $case])->call('extendEvidence')
        ->call('liftQuarantine')->assertSee('Quarantäne ist aufgehoben');
    expect($case->evidence()->first()->purge_after->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and($post->fresh()->quarantined_at)->toBeNull()->and($post->fresh()->held_at)->not->toBeNull();
    Livewire::actingAs($admin)->test(CaseDetail::class, ['case' => $case])->call('deleteEvidence');
    expect($case->evidence()->count())->toBe(0)
        ->and($case->events()->pluck('type')->all())->toContain(EventType::EvidenceDownloaded, EventType::EvidenceExtended,
            EventType::QuarantineLifted, EventType::EvidenceDeleted);
    $this->actingAs($admin)->get(route('moderation.cases.evidence', $case))->assertNotFound();
});

it('escalates automatically when the automated check sees a sexual reference to minors', function () {
    config(['oasis.moderation.enabled' => true]);
    ContentCheckAgent::fake([['insult' => 0, 'hate' => 0, 'threat' => 0, 'sexual' => 0.2, 'sexual_minors' => 0.9, 'reason' => 'Bezug zu Minderjährigen.']]);

    NetworkActions::publish(['body' => 'Ein auffälliger Text', 'visibility' => 'public'], N::member())->assertHasNoErrors();

    $case = ModerationCase::sole();
    expect($case->escalation)->toBe(Escalation::ChildAbuse)->and($case->escalated_by)->toBeNull()
        ->and(Post::sole()->quarantined_at)->not->toBeNull();
    Mail::assertSent(EscalationAlert::class);
});

it('puts escalated cases on top of the queue with their type', function () {
    [, $older] = reportedPost();
    [, $newer] = reportedPost();
    app(EscalateCase::class)->handle($newer, escalationStaff(), Escalation::ThreatToLife);

    Livewire::actingAs(escalationStaff())->test(Queue::class)->assertSeeInOrder(['#'.$newer->id, '#'.$older->id])
        ->assertSee('Gefahr für Leib und Leben');
});
