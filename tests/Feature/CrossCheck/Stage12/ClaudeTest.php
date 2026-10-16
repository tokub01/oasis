<?php

/*
 * Stage 12 cross-check (independent review of Claude's part): foundation, hints, public forms, moderation and admin
 * as Livewire components. Written from an attacker's point of view: every public method is called directly, with
 * foreign ids, manipulated or locked properties, expired deadlines and accounts that lost their rights mid-session.
 */

use App\Actions\Moderation\DecideCase;
use App\Actions\Moderation\ReviewComplaint;
use App\Enums\Role;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\TransparencyReports;
use App\Livewire\Admin\Votes;
use App\Livewire\Moderation\CaseDetail;
use App\Livewire\Moderation\ComplaintForm;
use App\Livewire\Moderation\ComplaintReview;
use App\Livewire\Moderation\Roles;
use App\Livewire\Notifications\Inbox;
use App\Livewire\Notifications\Settings;
use App\Livewire\Oasis\CancellationDeclaration;
use App\Livewire\Oasis\ContentNotice;
use App\Models\CancellationRequest;
use App\Models\ContentReport;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use App\Models\Notifications\NotificationPreference;
use App\Models\TransparencyReport;
use App\Models\User;
use App\Models\Vote;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\PublicForms;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
});

function s12cStaff(Role $role = Role::Moderator): User
{
    return User::factory()->create(['role' => $role, 'two_factor_secret' => encrypt('s12c'), 'two_factor_confirmed_at' => now()]);
}

/** A reported case about a member's Tropfen, with a distinctive reporter identity. */
function s12cReport(?User $subject = null): ContentReport
{
    $report = ContentReport::create([
        'url' => 'https://example.tld/post/s12c', 'reason' => 'Beleidigung.', 'name' => 'Geheimer Melder S12C',
        'email' => 'melder-s12c@example.tld', 'truthful_confirmed_at' => now(),
    ]);
    if ($subject) {
        $report->moderationCase->forceFill(['subject_user_id' => $subject->id])->save();
    }

    return $report;
}

function s12cDecide(User $decider, ?User $subject = null): ModerationDecision
{
    return app(DecideCase::class)->handle(s12cReport($subject)->moderationCase, $decider, [
        'outcome' => 'content_removed', 'ground' => 'community_rules', 'rule_reference' => 'Regel 1',
        'facts' => 'GEHEIME-TATSACHE-S12C', 'explanation' => 'BEGRUENDUNG-FUER-BETROFFENE-S12C',
    ]);
}

/** An open complaint by the reporter against a decision of $decider. */
function s12cComplaint(User $decider): ModerationComplaint
{
    $decision = s12cDecide($decider);
    Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision])
        ->set('reason', 'Falsch entschieden.')->call('submit')->assertHasNoErrors();

    return ModerationComplaint::sole();
}

function s12cHint(User $user): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create(['id' => $id, 'type' => 'App\Notifications\Test', 'data' => []]);

    return $id;
}

describe('foundation', function () {
    it('loads Livewire on public pages and keeps the statutory footer links on wire:navigate', function () {
        $html = $this->get(route('oasis.kuendigen'))->assertOk()->getContent();

        expect($html)->toContain('wire:snapshot')
            ->toMatch('#href="'.preg_quote(route('oasis.widerrufen'), '#').'" wire:navigate#')
            ->toMatch('#<script[^>]+livewire#i');
    });

    it('keeps logout a plain POST form (no wire:navigate) in the app shell', function () {
        $html = $this->actingAs(User::factory()->create())->get(route('notifications.index'))->assertOk()->getContent();

        expect($html)->toMatch('#<form method="POST" action="'.preg_quote(route('logout'), '#').'">#');
    });
});

describe('hints', function () {
    it('never marks a foreign hint as read', function () {
        $me = User::factory()->create();
        $foreign = s12cHint(User::factory()->create());

        Livewire::actingAs($me)->test(Inbox::class)->call('markRead', $foreign)->assertNotFound();

        expect(DB::table('notifications')->where('id', $foreign)->value('read_at'))->toBeNull();
    });

    it('marks only the own hints when marking all as read', function () {
        $me = User::factory()->create();
        $mine = s12cHint($me);
        $foreign = s12cHint(User::factory()->create());

        Livewire::actingAs($me)->test(Inbox::class)->call('markAllRead')->assertSet('status', 'Alle Hinweise sind gelesen.');

        expect(DB::table('notifications')->where('id', $mine)->value('read_at'))->not->toBeNull()
            ->and(DB::table('notifications')->where('id', $foreign)->value('read_at'))->toBeNull();
    });

    it('stores only known hint kinds and rejects non-boolean values', function () {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test(Settings::class)->set('kinds.follow', 'vielleicht')->call('save')
            ->assertHasErrors('kinds.follow');
        expect(NotificationPreference::where('user_id', $me->id)->exists())->toBeFalse();

        Livewire::actingAs($me)->test(Settings::class)->set('kinds.erfunden', true)->set('kinds.reply', false)->call('save')
            ->assertHasNoErrors();
        $kinds = NotificationPreference::where('user_id', $me->id)->sole()->kinds;
        expect(array_keys($kinds))->not->toContain('erfunden')->and($kinds['reply'])->toBeFalse();
    });
});

describe('public forms', function () {
    $cancellation = ['name' => 'Kim Kündig', 'email' => 'Kim@Example.TLD', 'type' => 'ordentlich', 'timing' => 'naechst'];

    it('stores a § 312k cancellation, drops inapplicable fields and unlocks only its own confirmation', function () use ($cancellation) {
        PublicForms::submit('kuendigen', [...$cancellation, 'reason' => 'nicht anwendbar', 'effective_date' => '1999-01-01'])
            ->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.kuendigen'));

        $stored = CancellationRequest::sole();
        expect($stored->email)->toBe('kim@example.tld')->and($stored->reason)->toBeNull()->and($stored->effective_date)->toBeNull()
            ->and(session('oasis.submitted.kuendigen'))->toBeArray()
            ->and(session('oasis.submitted.widerrufen'))->toBeNull();
    });

    it('rejects a manipulated contract and a filled honeypot without storing or mailing', function () use ($cancellation) {
        PublicForms::submit('kuendigen', [...$cancellation, 'contract' => 'Fremder Vertrag'])->assertHasErrors('form.contract');
        PublicForms::submit('kuendigen', [...$cancellation, 'website' => 'https://spam.tld'])->assertHasErrors('form.website');

        expect(CancellationRequest::count())->toBe(0);
        Mail::assertNothingSent();
    });

    it('shows no confirmation page without a submission', function () {
        foreach (PublicForms::COMPONENTS as $page => $component) {
            $this->get(route('oasis.bestaetigung.'.$page))->assertNotFound();
        }
    });

    it('limits each form to six submissions per minute and IP', function () use ($cancellation) {
        foreach (range(1, 6) as $attempt) {
            PublicForms::submit('kuendigen', [...$cancellation, 'email' => "k{$attempt}@example.tld"])->assertHasNoErrors();
        }
        PublicForms::submit('kuendigen', [...$cancellation, 'email' => 'k7@example.tld'])->assertHasErrors('throttle');

        expect(CancellationRequest::count())->toBe(6);
    });

    it('counts failed attempts too, like the former throttle middleware', function () {
        foreach (range(1, 6) as $attempt) {
            PublicForms::submit('gruendung', ['email' => 'kaputt'])->assertHasErrors('form.email');
        }
        PublicForms::submit('gruendung', ['email' => 'ok@example.tld', 'consent' => true])->assertHasErrors('throttle');

        expect(WaitlistEntry::count())->toBe(0);
    });

    it('never lets an exhausted waitlist budget block a cancellation', function () use ($cancellation) {
        foreach (range(1, 7) as $attempt) {
            PublicForms::submit('gruendung', ['email' => "w{$attempt}@example.tld", 'consent' => true]);
        }

        PublicForms::submit('kuendigen', $cancellation)->assertHasNoErrors()->assertRedirect(route('oasis.bestaetigung.kuendigen'));
        expect(CancellationRequest::count())->toBe(1);
    });

    it('rejects a javascript: link prefilled through ?url= in the DSA notice form', function () {
        Livewire::withQueryParams(['url' => 'javascript:alert(1)'])->test(ContentNotice::class)
            ->assertSet('form.url', 'javascript:alert(1)')
            ->set('form.reason', 'Grund')->set('form.name', 'A')->set('form.email', 'a@example.tld')->set('form.truthful', true)
            ->call('submit')->assertHasErrors('form.url');

        expect(ContentReport::count())->toBe(0);
    });

    it('prefills only the signed-in member\'s own contact data into the notice snapshot', function () {
        $me = User::factory()->create(['email' => 'ich-selbst@example.tld']);

        $html = $this->actingAs($me)->get(route('oasis.melden'))->assertOk()->assertSeeLivewire(ContentNotice::class)->getContent();
        expect($html)->toContain('ich-selbst@example.tld');
    });

    it('describes the cancellation e-mail field by its hint and error (aria-describedby)', function () {
        Livewire::test(CancellationDeclaration::class)->set('form.email', 'kaputt')->call('submit')
            ->assertSeeHtml('aria-describedby="k-mail-hinweis k-mail-error"');
    });
});

describe('moderation cases', function () {
    it('keeps reporter name and e-mail out of the case page and its snapshot', function () {
        $case = s12cReport()->moderationCase;

        $html = $this->actingAs(s12cStaff())->get(route('moderation.cases.show', $case))->assertOk()->getContent();

        expect($html)->not->toContain('melder-s12c@example.tld')->not->toContain('Geheimer Melder S12C');
    });

    it('closes the case page for members and for staff without confirmed 2FA', function () {
        $case = s12cReport()->moderationCase;

        $this->actingAs(User::factory()->create())->get(route('moderation.cases.show', $case))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Moderator]))->get(route('moderation.cases.show', $case))
            ->assertRedirect(route('app.settings'));
    });

    it('refuses assign and decide once the staff role is gone mid-session', function () {
        $staff = s12cStaff();
        $case = s12cReport()->moderationCase;
        $assign = Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case]);
        $decide = Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case])
            ->set('decision.outcome', 'content_removed')->set('decision.ground', 'community_rules')
            ->set('decision.rule_reference', 'R')->set('decision.facts', 'F')->set('decision.explanation', 'E');

        $staff->forceFill(['role' => Role::Member])->save();

        $assign->call('assign')->assertForbidden();
        $decide->call('decide')->assertForbidden();

        expect($case->fresh()->assigned_to)->toBeNull()->and($case->decisions()->count())->toBe(0);
    });

    it('refuses the decision when 2FA is switched off mid-session', function () {
        $staff = s12cStaff();
        $case = s12cReport()->moderationCase;
        $page = Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case]);

        $staff->forceFill(['two_factor_confirmed_at' => null])->save();

        $page->call('assign')->assertForbidden();
        expect($case->fresh()->assigned_to)->toBeNull();
    });

    it('keeps the case locked and assigns only to staff', function () {
        $staff = s12cStaff();
        $case = s12cReport()->moderationCase;
        $other = s12cReport()->moderationCase;
        $member = User::factory()->create();

        expect(fn () => Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case])->set('case', $other->id))
            ->toThrow(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case])
            ->set('assigned_to', $member->id)->call('assign')->assertHasErrors('assigned_to');
        expect($case->fresh()->assigned_to)->toBeNull();
    });

    it('requires a complete Art. 17 statement and refuses a second first decision', function () {
        $staff = s12cStaff();
        $case = s12cReport()->moderationCase;
        $page = Livewire::actingAs($staff)->test(CaseDetail::class, ['case' => $case]);

        $page->set('decision.outcome', 'erfunden')->call('decide')
            ->assertHasErrors(['decision.outcome', 'decision.ground', 'decision.rule_reference', 'decision.facts', 'decision.explanation']);
        expect($case->decisions()->count())->toBe(0);

        $page->set('decision.outcome', 'content_removed')->set('decision.ground', 'community_rules')
            ->set('decision.rule_reference', 'R')->set('decision.facts', 'F')->set('decision.explanation', 'E')
            ->call('decide')->assertHasNoErrors();
        $page->set('decision.outcome', 'no_action')->set('decision.ground', 'community_rules')
            ->set('decision.rule_reference', 'R')->set('decision.facts', 'F')->set('decision.explanation', 'E')
            ->call('decide')->assertForbidden();

        expect($case->decisions()->count())->toBe(1);
    });
});

describe('complaints', function () {
    it('shows a reporter neither facts nor reasons nor any e-mail address, also not in the snapshot', function () {
        $subject = User::factory()->create(['email' => 'betroffen-s12c@example.tld']);
        $decision = s12cDecide(s12cStaff(), $subject);
        $url = URL::signedRoute('moderation.complaint.create', ['decision' => $decision->id, 'recipient' => 'reporter']);

        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->not->toContain('GEHEIME-TATSACHE-S12C')->not->toContain('BEGRUENDUNG-FUER-BETROFFENE-S12C')
            ->not->toContain('melder-s12c@example.tld')->not->toContain('betroffen-s12c@example.tld');
    });

    it('rejects unsigned and tampered links and foreign accounts', function () {
        $subject = User::factory()->create();
        $decision = s12cDecide(s12cStaff(), $subject);
        $signed = URL::signedRoute('moderation.complaint.create', ['decision' => $decision->id, 'recipient' => 'reporter']);

        $this->get(route('moderation.complaint.create', ['decision' => $decision->id, 'recipient' => 'subject']))->assertForbidden();
        $this->get(str_replace('recipient=reporter', 'recipient=subject', $signed))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('moderation.complaint.account', $decision))->assertForbidden();
        $this->actingAs($subject)->get(route('moderation.complaint.account', $decision))->assertOk();
    });

    it('keeps the recipient locked', function () {
        $decision = s12cDecide(s12cStaff(), User::factory()->create());

        expect(fn () => Livewire::withQueryParams(['recipient' => 'reporter'])
            ->test(ComplaintForm::class, ['decision' => $decision])->set('recipient', 'subject'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('refuses a complaint once the six-month deadline has passed while the form was open', function () {
        $decision = s12cDecide(s12cStaff());
        $form = Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision]);

        $this->travel(7)->months();

        $form->set('reason', 'Zu spät, aber versucht.')->call('submit')->assertForbidden();
        expect(ModerationComplaint::count())->toBe(0);
    });

    it('refuses a complaint against a decision that a newer one replaced meanwhile', function () {
        $decision = s12cDecide(s12cStaff());
        $form = Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision]);
        $form->set('reason', 'Erster Widerspruch.')->call('submit')->assertHasNoErrors();
        app(ReviewComplaint::class)->handle(ModerationComplaint::sole(), s12cStaff(), [
            'result' => 'overturned', 'outcome' => 'no_action', 'ground' => 'community_rules', 'rule_reference' => 'R',
            'facts' => 'F', 'explanation' => 'Neu entschieden.',
        ]);

        $url = URL::signedRoute('moderation.complaint.create', ['decision' => $decision->id, 'recipient' => 'reporter']);
        $this->get($url)->assertForbidden();
    });

    it('accepts one complaint per recipient and decision', function () {
        $decision = s12cDecide(s12cStaff());
        $form = Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision]);

        $form->set('reason', 'Erster.')->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
        Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision])
            ->set('reason', 'Zweiter.')->call('submit')->assertHasErrors('reason');

        expect(ModerationComplaint::count())->toBe(1);
    });

    it('limits complaints to five attempts per minute and IP, invalid ones included', function () {
        $decision = s12cDecide(s12cStaff());
        $form = Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision]);

        foreach (range(1, 5) as $attempt) {
            $form->set('reason', '')->call('submit')->assertHasErrors('reason');
        }
        $form->set('reason', 'Jetzt mit Begründung.')->call('submit')->assertHasErrors('reason')
            ->assertSee('Zu viele Beschwerden');

        expect(ModerationComplaint::count())->toBe(0);
    });

    it('does not let a client flip `submitted` to show the thank-you text without a complaint', function () {
        $decision = s12cDecide(s12cStaff());

        expect(fn () => Livewire::withQueryParams(['recipient' => 'reporter'])->test(ComplaintForm::class, ['decision' => $decision])
            ->set('submitted', true))->toThrow(CannotUpdateLockedPropertyException::class);

        expect(ModerationComplaint::count())->toBe(0);
    });
});

describe('complaint review', function () {
    it('never lets the original decider review, even by calling the method directly', function () {
        $decider = s12cStaff();
        $complaint = s12cComplaint($decider);

        Livewire::actingAs($decider)->test(ComplaintReview::class, ['complaint' => $complaint])
            ->set('result', 'upheld')->set('explanation', 'Bleibt.')->call('review')->assertForbidden();

        expect($complaint->fresh()->status->value)->toBe('open');
    });

    it('requires a full statement for "overturned", rejects unknown results and closes after one review', function () {
        $complaint = s12cComplaint(s12cStaff());
        $page = Livewire::actingAs(s12cStaff())->test(ComplaintReview::class, ['complaint' => $complaint]);

        $page->set('result', 'geloescht')->call('review')->assertHasErrors('result');
        $page->set('result', 'overturned')->call('review')->assertHasErrors(['decision.outcome', 'decision.explanation']);
        expect($complaint->fresh()->status->value)->toBe('open');

        $page->set('result', 'upheld')->set('explanation', 'Die Entscheidung bleibt.')->call('review')->assertHasNoErrors();
        $page->set('result', 'upheld')->set('explanation', 'Nochmal.')->call('review')->assertForbidden();

        expect($complaint->fresh()->review_explanation)->toBe('Die Entscheidung bleibt.');
    });

    it('closes the review page to members', function () {
        $complaint = s12cComplaint(s12cStaff());

        $this->actingAs(User::factory()->create())->get(route('moderation.complaints.show', $complaint))->assertForbidden();
    });
});

describe('roles', function () {
    it('refuses changing the own role, unknown roles and saves after losing the admin role', function () {
        $admin = s12cStaff(Role::Admin);
        $member = User::factory()->create();
        Livewire::actingAs($admin)->test(Roles::class)
            ->set('roles.'.$admin->id, 'member')->call('save', $admin->id)->assertForbidden();
        Livewire::actingAs($admin)->test(Roles::class)
            ->set('roles.'.$member->id, 'kaiser')->call('save', $member->id)->assertHasErrors('roles.'.$member->id);

        $page = Livewire::actingAs($admin)->test(Roles::class)->set('roles.'.$member->id, 'admin');
        $admin->forceFill(['role' => Role::Moderator])->save();
        $page->call('save', $member->id)->assertForbidden();

        expect($member->fresh()->role)->toBe(Role::Member)->and($admin->fresh()->role)->toBe(Role::Moderator);
    });

    it('closes the roles page to moderators', function () {
        $this->actingAs(s12cStaff())->get(route('moderation.roles.index'))->assertForbidden();
    });
});

describe('admin', function () {
    it('closes the admin area to moderators', function () {
        $moderator = s12cStaff();

        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.votes.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.transparency.index'))->assertForbidden();
    });

    it('falls back to 30 days for an unknown period', function () {
        Livewire::actingAs(s12cStaff(Role::Admin))->withQueryParams(['tage' => '14'])->test(Dashboard::class)
            ->assertSet('days', 30);
    });

    it('does not fail with a server error for a non-numeric period in the URL', function () {
        $this->actingAs(s12cStaff(Role::Admin))->get(route('admin.dashboard', ['tage' => 'abc']))->assertStatus(200);
    });

    it('refuses to create a vote or publish figures after losing the admin role', function () {
        $admin = s12cStaff(Role::Admin);
        $votes = Livewire::actingAs($admin)->test(Votes::class)
            ->set('title', 'Frage')->set('description', 'Text')->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(3)->format('Y-m-d\TH:i'))->set('options', ['Ja', 'Nein']);
        $reports = Livewire::actingAs($admin)->test(TransparencyReports::class);

        $admin->forceFill(['role' => Role::Moderator])->save();

        $votes->call('create')->assertForbidden();
        $reports->call('publish')->assertForbidden();

        expect(Vote::count())->toBe(0)->and(TransparencyReport::count())->toBe(0);
    });

    it('rejects too many answers and duplicate-only answers', function () {
        $page = Livewire::actingAs(s12cStaff(Role::Admin))->test(Votes::class)
            ->set('title', 'Frage')->set('description', 'Text')->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(3)->format('Y-m-d\TH:i'));

        $page->set('options', array_fill(0, 9, 'x'))->call('create')->assertHasErrors('options');
        $page->set('options', ['Ja', ' Ja ', '', '', '', ''])->call('create')->assertHasErrors('options');

        expect(Vote::count())->toBe(0);
    });

    it('publishes nothing without a named cost item', function () {
        Livewire::actingAs(s12cStaff(Role::Admin))->test(TransparencyReports::class)
            ->set('items', [['label' => null, 'amount' => null]])->call('publish')->assertHasErrors('items');

        expect(TransparencyReport::count())->toBe(0);
    });
});
