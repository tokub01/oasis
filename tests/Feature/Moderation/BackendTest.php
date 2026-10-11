<?php

use App\Actions\Moderation\DecideCase;
use App\Actions\Moderation\NotifyDecision;
use App\Actions\Moderation\OpenCase;
use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\ComplaintStatus;
use App\Enums\Moderation\EventType;
use App\Enums\Moderation\Outcome;
use App\Enums\Role;
use App\Livewire\Moderation\Queue;
use App\Mail\Moderation\ComplaintResult;
use App\Mail\Moderation\DecisionNotice;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Queries\Moderation\TransparencyQuery;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/* Backend acceptance and regression tests: identity, state transitions, second reviewer, audit and retention. */
beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

/** Create a staff account with confirmed 2FA without changing public account mass-assignment rules. */
function moderationStaff(Role $role = Role::Moderator): User
{
    return User::factory()->create(['role' => $role, 'two_factor_secret' => encrypt('test-second-factor'), 'two_factor_confirmed_at' => now()]);
}

/** A public report automatically opens its observer-backed case. */
function moderationReport(): ContentReport
{
    return ContentReport::create(['url' => 'https://example.tld/post/42', 'reason' => 'Eine begründete Meldung.',
        'name' => 'Meldende Person', 'email' => 'reporter@example.tld', 'truthful_confirmed_at' => now()]);
}

/** Full Art. 17 decision input with deliberately private facts to check audience separation. */
function moderationDecisionData(array $overrides = []): array
{
    return array_replace(['outcome' => 'content_removed', 'ground' => 'community_rules', 'rule_reference' => 'Regel 1',
        'facts' => 'PRIVATE-FACTS', 'explanation' => 'PRIVATE-EXPLANATION'], $overrides);
}

/** Create an initial decision through the same action used by staff HTTP requests. */
function moderationDecided(?User $staff = null): ModerationDecision
{
    return app(DecideCase::class)->handle(moderationReport()->moderationCase, $staff ?? moderationStaff(), moderationDecisionData());
}

/** A signed link's recipient selects a stored email without exposing that email in the URL. */
function moderationComplaintUrl(ModerationDecision $decision, string $route = 'store', string $recipient = 'reporter'): string
{
    return URL::signedRoute('moderation.complaint.'.$route, ['decision' => $decision->id, 'recipient' => $recipient]);
}

it('defaults new accounts to members and prevents mass assigned role elevation', function () {
    $user = User::factory()->create();
    expect($user->fresh()->role)->toBe(Role::Member);
    $user->fill(['role' => 'admin'])->save();
    expect($user->fresh()->role)->toBe(Role::Member);
    expect(Role::Moderator->label())->toBe('Moderation');
    expect(Role::Member->isStaff())->toBeFalse();
});

it('denies members and unauthenticated visitors access to moderation', function () {
    $this->get(route('moderation.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('moderation.index'))->assertForbidden();
});

it('requires configured and confirmed two factor authentication for staff', function ($secret, $confirmed) {
    $staff = User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => $confirmed ? now() : null]);
    $this->actingAs($staff)->get(route('moderation.index'))->assertRedirect(route('app.settings'))
        ->assertSessionHas('status', fn ($message) => str_contains($message, 'Zwei-Faktor'));
})->with([[null, false], ['configured-secret', false], [null, true]]);

it('admits confirmed staff and keeps role administration admin only', function () {
    $this->actingAs(moderationStaff())->get(route('moderation.index'))->assertOk();
    $this->get(route('moderation.roles.index'))->assertForbidden();
    $this->actingAs(moderationStaff(Role::Admin))->get(route('moderation.roles.index'))->assertOk();
});

it('bootstraps roles by operator command and rejects unknown accounts and values', function () {
    $user = User::factory()->create(['email' => 'first@example.tld']);
    $this->artisan('oasis:role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();
    expect($user->fresh()->role)->toBe(Role::Admin);
    $this->artisan('oasis:role', ['email' => $user->email, 'role' => 'root'])->assertFailed();
    $this->artisan('oasis:role', ['email' => 'absent@example.tld', 'role' => 'admin'])->assertFailed();
});

it('lets only admins change roles through the validated endpoint', function () {
    $target = User::factory()->create();
    $this->actingAs(moderationStaff())->put(route('moderation.roles.update', $target), ['role' => 'admin'])->assertForbidden();
    $this->actingAs(moderationStaff(Role::Admin))->put(route('moderation.roles.update', $target), ['role' => 'moderator'])->assertRedirect();
    expect($target->fresh()->role)->toBe(Role::Moderator);
    $this->put(route('moderation.roles.update', $target), ['role' => 'root'])->assertSessionHasErrors('role');
});

it('opens one case and audit event automatically for a content report', function () {
    $report = moderationReport();
    $case = $report->moderationCase;
    expect($case->source->is($report))->toBeTrue();
    expect($case->status)->toBe(CaseStatus::Open);
    expect($case->subject_url)->toBe($report->url);
    expect($case->events->sole()->type)->toBe(EventType::Opened);
    app(OpenCase::class)->handle($report);
    expect(ModerationCase::count())->toBe(1);
    expect(ModerationEvent::count())->toBe(1);
    expect($report->toArray())->not->toHaveKeys(['email', 'name']);
});

it('opens the case from the public HTTP report flow and sends receipts', function () {
    $this->post(route('oasis.melden.store'), ['url' => 'https://example.tld/a', 'reason' => 'Verstoß',
        'name' => 'Melder', 'email' => 'notice@example.tld', 'truthful' => '1'])->assertRedirect();
    expect(ModerationCase::count())->toBe(1);
    expect(ModerationEvent::first()->type)->toBe(EventType::Opened);
    Mail::assertSentCount(2);
});

it('assigns only staff and records the same transaction state change', function () {
    $case = moderationReport()->moderationCase;
    $staff = moderationStaff();
    $assignee = moderationStaff();
    $this->actingAs($staff)->post(route('moderation.cases.assign', $case), ['assigned_to' => $assignee->id])->assertRedirect();
    expect($case->fresh()->assigned_to)->toBe($assignee->id);
    expect($case->fresh()->status)->toBe(CaseStatus::InReview);
    expect($case->events()->where('type', 'assigned')->sole()->actor_id)->toBe($staff->id);
    $this->post(route('moderation.cases.assign', $case), ['assigned_to' => User::factory()->create()->id])
        ->assertSessionHasErrors('assigned_to');
    expect($case->events()->where('type', 'assigned')->count())->toBe(1);
});

it('saves a reasoned human decision with audit and separate reporter and affected notices', function () {
    $case = moderationReport()->moderationCase;
    $affected = User::factory()->create(['email' => 'affected@example.tld']);
    $case->update(['subject_user_id' => $affected->id]);
    $this->actingAs(moderationStaff())->post(route('moderation.cases.decide', $case), moderationDecisionData())->assertRedirect();
    $decision = $case->decisions()->sole();
    expect($decision->automated)->toBeFalse();
    expect($decision->notified_at)->not->toBeNull();
    expect($case->fresh()->status)->toBe(CaseStatus::Decided);
    expect($case->events()->where('type', 'decided')->sole()->data['decision_id'])->toBe($decision->id);
    expect($case->events()->where('type', 'notified')->count())->toBe(2);
    Mail::assertSent(DecisionNotice::class, fn ($mail) => $mail->recipient === 'reporter' && $mail->hasTo('reporter@example.tld')
        && ! str_contains($mail->render(), 'PRIVATE-FACTS') && ! str_contains($mail->render(), 'PRIVATE-EXPLANATION'));
    Mail::assertSent(DecisionNotice::class, fn ($mail) => $mail->recipient === 'subject' && $mail->hasTo($affected->email)
        && str_contains($mail->render(), 'PRIVATE-FACTS') && str_contains($mail->render(), 'Art. 21 DSA'));
});

it('rejects incomplete German validation and repeated initial decisions', function () {
    $case = moderationReport()->moderationCase;
    $this->actingAs(moderationStaff())->post(route('moderation.cases.decide', $case), ['outcome' => 'bad'])
        ->assertSessionHasErrors(['outcome', 'facts', 'ground', 'rule_reference', 'explanation']);
    expect($case->decisions()->count())->toBe(0);
    $this->post(route('moderation.cases.decide', $case), moderationDecisionData())->assertRedirect();
    $this->post(route('moderation.cases.decide', $case), moderationDecisionData())->assertForbidden();
    expect($case->decisions()->count())->toBe(1);
});

it('sends delivery retries only to recipients not yet recorded', function () {
    $decision = moderationDecided();
    Mail::fake();
    app(NotifyDecision::class)->handle($decision);
    Mail::assertNothingSent();
    $this->artisan('oasis:moderation-notify')->assertSuccessful();
    Mail::assertNothingSent();
});

it('retains committed decisions after a mail transport failure and can retry', function () {
    $case = moderationReport()->moderationCase;
    $notifier = Mockery::mock(NotifyDecision::class);
    $notifier->shouldReceive('handle')->once()->andThrow(new RuntimeException('mail unavailable'));
    app()->instance(NotifyDecision::class, $notifier);
    expect(fn () => app(DecideCase::class)->handle($case, moderationStaff(), moderationDecisionData()))->toThrow(RuntimeException::class);
    expect($case->fresh()->status)->toBe(CaseStatus::Decided);
    expect($case->decisions()->sole()->notified_at)->toBeNull();
    expect($case->events()->where('type', 'decided')->count())->toBe(1);
    app()->forgetInstance(NotifyDecision::class);
    app(NotifyDecision::class)->handle($case->decisions()->sole());
    Mail::assertSent(DecisionNotice::class);
});

it('requires signed links and never exposes full reasons to a reporter', function () {
    $decision = moderationDecided();
    $this->get(route('moderation.complaint.create', $decision))->assertForbidden();
    $this->post(route('moderation.complaint.store', $decision), ['reason' => 'Nein'])->assertForbidden();
    $this->get(moderationComplaintUrl($decision, 'create'))->assertOk()->assertDontSee('PRIVATE-EXPLANATION')->assertDontSee('PRIVATE-FACTS');
    $this->get(moderationComplaintUrl($decision, 'create').'&recipient=subject')->assertForbidden();
});

it('accepts a guest complaint with stored identity and opens its audit trail', function () {
    $decision = moderationDecided();
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Bitte erneut prüfen.', 'email' => 'attacker@example.tld'])
        ->assertOk()->assertViewIs('moderation.complaint.thanks');
    $complaint = ModerationComplaint::sole();
    expect($complaint->submitted_by)->toBeNull();
    expect($complaint->submitter_email)->toBe('reporter@example.tld');
    expect($decision->case->fresh()->status)->toBe(CaseStatus::ComplaintOpen);
    expect($decision->case->events()->where('type', 'complaint_received')->sole()->data['complaint_id'])->toBe($complaint->id);
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Duplicate'])->assertSessionHasErrors('reason');
});

it('lets only the affected signed in account use the account complaint route', function () {
    $decision = moderationDecided();
    $affected = User::factory()->create();
    $decision->case->update(['subject_user_id' => $affected->id]);
    $this->actingAs(User::factory()->create())->get(route('moderation.complaint.account', $decision))->assertForbidden();
    $this->actingAs($affected)->get(route('moderation.complaint.account', $decision))->assertOk()->assertSee('PRIVATE-EXPLANATION');
    $this->post(route('moderation.complaint.account.store', $decision), ['reason' => 'Bitte prüfen'])->assertOk();
    expect(ModerationComplaint::sole()->submitted_by)->toBe($affected->id);
});

it('keeps the full six month complaint window after notice and expires it afterwards', function () {
    $decision = moderationDecided();
    $decision->update(['decided_at' => now()->subYear(), 'notified_at' => now()]);
    $deadline = $decision->fresh()->notified_at->copy()->addMonthsNoOverflow(6);
    $this->travelTo($deadline);
    $this->get(moderationComplaintUrl($decision, 'create'))->assertOk();
    $this->travelTo($deadline->copy()->addSecond());
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Zu spät'])->assertForbidden();
});

it('rate limits public complaint posts independently of validation', function () {
    $decision = moderationDecided();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(moderationComplaintUrl($decision), ['reason' => ''])->assertSessionHasErrors('reason');
    }
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Valid'])->assertStatus(429)->assertSee('Zu viele Beschwerden');
});

it('denies the original moderator and admin from deciding their own complaint', function (Role $role) {
    $staff = moderationStaff($role);
    $decision = moderationDecided($staff);
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Widerspruch'])->assertOk();
    $complaint = ModerationComplaint::sole();
    $this->actingAs($staff)->post(route('moderation.complaints.decide', $complaint), ['result' => 'upheld', 'explanation' => 'Bestätigt'])
        ->assertForbidden();
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Open);
})->with([Role::Moderator, Role::Admin]);

it('upholds a complaint with a second person and sends the reasoned result', function () {
    $decision = moderationDecided();
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Widerspruch'])->assertOk();
    $complaint = ModerationComplaint::sole();
    $reviewer = moderationStaff();
    $this->actingAs($reviewer)->post(route('moderation.complaints.decide', $complaint), ['result' => 'upheld', 'explanation' => 'Die Entscheidung bleibt bestehen.'])
        ->assertRedirect();
    expect($complaint->fresh()->reviewed_by)->toBe($reviewer->id);
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Upheld);
    expect($decision->case->fresh()->status)->toBe(CaseStatus::Closed);
    expect($decision->case->decisions()->count())->toBe(1);
    Mail::assertSent(ComplaintResult::class, fn ($mail) => str_contains($mail->render(), 'Die Entscheidung bleibt bestehen.'));
    $this->post(route('moderation.complaints.decide', $complaint), ['result' => 'upheld', 'explanation' => 'Erneut'])->assertForbidden();
});

it('overturns by superseding the original decision without rewriting its history', function () {
    $decision = moderationDecided();
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Widerspruch'])->assertOk();
    $complaint = ModerationComplaint::sole();
    $this->actingAs(moderationStaff())->post(route('moderation.complaints.decide', $complaint),
        moderationDecisionData(['result' => 'overturned', 'outcome' => 'no_action', 'explanation' => 'Kein Regelverstoß']))->assertRedirect();
    $replacement = $decision->case->decisions()->latest('id')->first();
    expect($replacement->supersedes_decision_id)->toBe($decision->id);
    expect($replacement->is_complaint_review)->toBeTrue();
    expect($replacement->outcome)->toBe(Outcome::NoAction);
    expect($decision->fresh()->outcome)->toBe(Outcome::ContentRemoved);
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Overturned);
    $this->get(moderationComplaintUrl($decision, 'create'))->assertForbidden();
    $this->get(moderationComplaintUrl($replacement, 'create'))->assertOk();
    Mail::assertSent(ComplaintResult::class);
    Mail::fake();
    app(NotifyDecision::class)->handle($decision);
    Mail::assertNothingSent();
});

it('rejects audit model mutations and bulk Eloquent bypasses', function () {
    $event = moderationReport()->moderationCase->events()->sole();
    expect(fn () => $event->update(['data' => ['tampered' => true]]))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    expect(fn () => ModerationEvent::whereKey($event->id)->update(['data' => '{}']))->toThrow(LogicException::class);
    expect(fn () => ModerationEvent::whereKey($event->id)->delete())->toThrow(LogicException::class);
    expect(fn () => ModerationEvent::whereKey($event->id)->increment('actor_id'))->toThrow(LogicException::class);
    expect(fn () => ModerationEvent::upsert([['id' => $event->id, 'data' => '{}']], ['id']))->toThrow(LogicException::class);
    expect($event->fresh()->data)->toBe(['source' => 'content_report']);
});

it('retains unresolved cases and complaint windows and cascades expired case history on pruning', function () {
    config(['oasis.privacy.records_years' => 1]);
    $old = now()->subYears(2);
    $open = moderationReport()->moderationCase;
    $open->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
    $eligible = moderationDecided();
    $eligible->update(['notified_at' => $old, 'decided_at' => $old]);
    $eligible->case->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
    $recent = moderationDecided();
    $recent->case->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
    $ids = (new ModerationCase)->prunable()->pluck('id')->all();
    expect($ids)->toBe([$eligible->case_id]);
    $this->artisan('model:prune', ['--model' => [ModerationCase::class]])->assertSuccessful();
    expect(ModerationCase::find($eligible->case_id))->toBeNull();
    expect(ModerationDecision::find($eligible->id))->toBeNull();
    expect(ModerationEvent::where('case_id', $eligible->case_id)->count())->toBe(0);
    expect(ModerationCase::find($open->id))->not->toBeNull();
    expect(ModerationCase::find($recent->case_id))->not->toBeNull();
    config(['oasis.privacy.records_years' => null]);
    expect((new ModerationCase)->prunable()->count())->toBe(0);
});

it('keeps source evidence until its retained moderation case is deleted', function () {
    config(['oasis.privacy.records_years' => 1]);
    $report = moderationReport();
    $report->forceFill(['created_at' => now()->subYears(2)])->save();
    expect((new ContentReport)->prunable()->whereKey($report->id)->exists())->toBeFalse();
    $report->moderationCase->delete();
    expect((new ContentReport)->prunable()->whereKey($report->id)->exists())->toBeTrue();
});

it('preserves audit and decisions when a staff account is deleted', function () {
    $staff = moderationStaff();
    $decision = moderationDecided($staff);
    $staff->delete();
    expect($decision->fresh()->decided_by)->toBeNull();
    expect($decision->case->events()->where('type', 'decided')->sole()->actor_id)->toBeNull();
});

it('counts period decisions and complaints and median initial decision time without identities', function () {
    $start = now()->startOfDay();
    $this->travelTo($start);
    $one = moderationReport()->moderationCase;
    $two = moderationReport()->moderationCase;
    $this->travelTo($start->copy()->addHour());
    $decision = app(DecideCase::class)->handle($one, moderationStaff(), moderationDecisionData());
    $this->travelTo($start->copy()->addHours(3));
    app(DecideCase::class)->handle($two, moderationStaff(), moderationDecisionData(['outcome' => 'no_action', 'ground' => 'illegal_content']));
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Widerspruch'])->assertOk();
    $this->actingAs(moderationStaff())->post(route('moderation.complaints.decide', ModerationComplaint::sole()),
        moderationDecisionData(['result' => 'overturned', 'outcome' => 'no_action']))->assertRedirect();
    $result = app(TransparencyQuery::class)->forPeriod($start, $start->copy()->addDay());
    expect($result['notices_received'])->toBe(2);
    expect($result['decisions_by_outcome'])->toBe(['content_removed' => 1, 'no_action' => 2]);
    expect($result['decisions_by_ground']['community_rules'])->toBe(2);
    expect($result['complaints_received'])->toBe(1);
    expect($result['complaints_overturned'])->toBe(1);
    expect((float) $result['median_time_to_decision_seconds'])->toBe(7200.0);
    expect(json_encode($result))->not->toContain('reporter@example.tld');
    expect(app(TransparencyQuery::class)->forPeriod($start->copy()->addDay(), $start->copy()->addDays(2))['median_time_to_decision_seconds'])->toBeNull();
});

it('accepts both parties complaints and keeps the case open until both receive an independent review', function () {
    $original = moderationStaff();
    $decision = moderationDecided($original);
    $affected = User::factory()->create();
    $decision->case->update(['subject_user_id' => $affected->id]);
    $this->post(moderationComplaintUrl($decision), ['reason' => 'Meldende Person widerspricht'])->assertOk();
    $this->post(moderationComplaintUrl($decision, 'store', 'subject'), ['reason' => 'Betroffene Person widerspricht'])->assertOk();
    $complaints = ModerationComplaint::orderBy('id')->get();
    expect($complaints)->toHaveCount(2);
    $this->actingAs(moderationStaff())->post(route('moderation.complaints.decide', $complaints[0]),
        moderationDecisionData(['result' => 'overturned', 'outcome' => 'no_action']))->assertRedirect();
    expect($decision->case->fresh()->status)->toBe(CaseStatus::ComplaintOpen);
    $this->actingAs($original)->post(route('moderation.complaints.decide', $complaints[1]),
        ['result' => 'upheld', 'explanation' => 'Bestätigt'])->assertForbidden();
    $this->actingAs(moderationStaff())->post(route('moderation.complaints.decide', $complaints[1]),
        ['result' => 'upheld', 'explanation' => 'Historische Entscheidung geprüft'])->assertRedirect();
    expect($decision->case->fresh()->status)->toBe(CaseStatus::Closed);
    expect($decision->case->fresh()->latestDecision->outcome)->toBe(Outcome::NoAction);
});

it('denies subsequent livewire queue updates after a staff role is revoked', function () {
    $staff = moderationStaff();
    $queue = Livewire::actingAs($staff)->test(Queue::class)->assertOk();

    User::whereKey($staff->id)->update(['role' => Role::Member]);
    // A new HTTP request restores the current account from the database, without mounting the queue again.
    Livewire::actingAs($staff->fresh());

    $queue->set('status', 'all')->assertForbidden();
});
