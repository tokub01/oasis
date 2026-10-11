<?php

/*
 * UI checks for the moderation area (stage 4a): queue, case page, complaint review, role management and the
 * public complaint form render the agreed data, keep reporter contact data hidden, and the Livewire queue stays
 * closed for anyone who loses the staff role mid-session.
 */

use App\Actions\Moderation\DecideCase;
use App\Enums\Role;
use App\Livewire\Moderation\Queue;
use App\Models\ContentReport;
use App\Models\ModerationDecision;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

function uiStaff(Role $role = Role::Moderator, array $attributes = []): User
{
    return User::factory()->create([
        'role' => $role,
        'two_factor_secret' => encrypt('ui-test-secret'),
        'two_factor_confirmed_at' => now(),
        ...$attributes,
    ]);
}

function uiReport(string $url = 'https://example.tld/post/7'): ContentReport
{
    return ContentReport::create([
        'url' => $url, 'reason' => 'Beleidigender Tropfen.', 'name' => 'Geheime Meldende',
        'email' => 'geheim-meldend@example.tld', 'truthful_confirmed_at' => now(),
    ]);
}

function uiDecision(User $decider): ModerationDecision
{
    return app(DecideCase::class)->handle(uiReport()->moderationCase, $decider, [
        'outcome' => 'content_removed', 'ground' => 'community_rules', 'rule_reference' => 'Regel 1: Sei respektvoll',
        'facts' => 'Tatsachen', 'explanation' => 'Der Tropfen beleidigt eine Person.',
    ]);
}

describe('queue', function () {
    it('lists open cases without reporter contact data', function () {
        uiReport('https://example.tld/post/queue-test');

        $this->actingAs(uiStaff())->get(route('moderation.index'))->assertOk()
            ->assertSee('Warteschlange')
            ->assertSee('https://example.tld/post/queue-test')
            ->assertDontSee('Geheime Meldende')
            ->assertDontSee('geheim-meldend@example.tld');
    });

    it('filters by the reported URL', function () {
        uiReport('https://example.tld/post/alpha');
        uiReport('https://example.tld/post/beta');

        Livewire::actingAs(uiStaff())->test(Queue::class)
            ->set('search', 'alpha')
            ->assertSee('post/alpha')
            ->assertDontSee('post/beta');
    });

    it('closes the queue for further Livewire updates once the staff role is gone', function () {
        $staff = uiStaff();
        $component = Livewire::actingAs($staff)->test(Queue::class)->assertOk();

        $staff->forceFill(['role' => Role::Member])->save();

        $component->set('status', 'all')->assertForbidden();
    });
});

describe('case page', function () {
    it('shows the report, the decision form and the staff select', function () {
        $case = uiReport()->moderationCase;

        $this->actingAs(uiStaff())->get(route('moderation.cases.show', $case))->assertOk()
            ->assertSee('Beleidigender Tropfen.')
            ->assertSee(route('moderation.cases.decide', $case), false)
            ->assertSee(route('moderation.cases.assign', $case), false)
            ->assertSee('name="explanation"', false)
            ->assertDontSee('geheim-meldend@example.tld');
    });

    it('shows past decisions and hides the form once decided', function () {
        $decision = uiDecision(uiStaff());

        $this->actingAs(uiStaff())->get(route('moderation.cases.show', $decision->case))->assertOk()
            ->assertSee('Regel 1: Sei respektvoll')
            ->assertDontSee(route('moderation.cases.decide', $decision->case), false);
    });
});

describe('complaints', function () {
    it('offers the public complaint form with deadline and the signed action', function () {
        $decision = uiDecision(uiStaff());
        $url = URL::signedRoute('moderation.complaint.create', ['decision' => $decision->id, 'recipient' => 'reporter']);

        $html = $this->get($url)->assertOk()->assertSee('Widerspruch einlegen')->assertSee('name="reason"', false)->getContent();

        expect($html)->toContain('signature=')->not->toContain('name="email"');
    });

    it('does not let the original decider review the complaint', function () {
        $decider = uiStaff();
        $decision = uiDecision($decider);
        $this->post(URL::signedRoute('moderation.complaint.store', ['decision' => $decision->id, 'recipient' => 'reporter']), [
            'reason' => 'Ich halte die Entscheidung für falsch.',
        ]);
        $complaint = $decision->complaints()->firstOrFail();

        $this->actingAs($decider)->get(route('moderation.complaints.show', $complaint))->assertOk()
            ->assertSee('prüft eine andere Person')
            ->assertDontSee(route('moderation.complaints.decide', $complaint), false);

        $this->actingAs(uiStaff())->get(route('moderation.complaints.show', $complaint))->assertOk()
            ->assertSee(route('moderation.complaints.decide', $complaint), false);
    });
});

describe('roles', function () {
    it('lets admins change roles but not their own', function () {
        $admin = uiStaff(Role::Admin);
        $member = User::factory()->create(['display_name' => 'Rollen Kandidat']);

        $this->actingAs($admin)->get(route('moderation.roles.index'))->assertOk()
            ->assertSee('Rollen Kandidat')
            ->assertSee(route('moderation.roles.update', $member), false)
            ->assertSee('Das bist du');
    });

    it('is closed for moderators', function () {
        $this->actingAs(uiStaff())->get(route('moderation.roles.index'))->assertForbidden();
    });
});

describe('navigation', function () {
    it('shows the moderation link only to staff', function () {
        $this->actingAs(User::factory()->create())->get(route('app.home'))->assertDontSee(route('moderation.index'), false);
        $this->actingAs(uiStaff())->get(route('app.home'))->assertSee(route('moderation.index'), false);
    });
});
