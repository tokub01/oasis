<?php

use App\Actions\Governance\ManageVotes;
use App\Enums\Role;
use App\Models\ChatIdentity;
use App\Models\GiftMembership;
use App\Models\TransparencyReport;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteParticipation;
use App\Queries\Admin\AdminStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\NetworkFixtures as N;

/* Stage 6: admin access, statistics module, transparency reports and secret member votes. */
beforeEach(fn () => $this->withoutVite());

function staffUser(Role $role): User
{
    return User::factory()->create(['role' => $role, 'two_factor_secret' => encrypt('test-second-factor'), 'two_factor_confirmed_at' => now()]);
}

/** A member with an active gifted membership (eligible to vote). */
function votingMember(): User
{
    $user = N::member();
    GiftMembership::factory()->create(['redeemed_by_user_id' => $user->id, 'redeemed_at' => now(), 'expires_at' => now()->addYear()]);

    return $user;
}

function openVote(User $admin, array $options = ['Mehr Patenplätze', 'Eine Rücklage']): Vote
{
    return app(ManageVotes::class)->create($admin, [
        'title' => 'Wohin mit dem Überschuss?', 'description' => 'Erklärung.',
        'starts_at' => now()->subHour(), 'ends_at' => now()->addWeek(), 'options' => $options,
    ]);
}

it('opens the admin area only to admins with confirmed 2FA', function () {
    $this->actingAs(N::member())->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(staffUser(Role::Moderator))->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get(route('admin.dashboard'))->assertRedirect(); // staff middleware sends to 2FA setup

    $this->actingAs(staffUser(Role::Admin))->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Mitglieder und Beiträge')->assertSee('nur Metadaten')->assertSee(route('admin.transparency.index'));
    $this->get(route('admin.dashboard', ['tage' => 7]))->assertOk()->assertSee('letzten 7 Tage');
    $this->get(route('admin.dashboard', ['tage' => 3]))->assertSessionHasErrors('tage');
});

it('aggregates network and chat metadata for the chosen period', function () {
    $author = N::member();
    N::post($author);
    $this->travel(-40)->days();
    N::post($author);
    $this->travelBack();
    N::oasis($author);
    ChatIdentity::forceCreate(['user_id' => $author->id, 'locked_identity' => [], 'public_keys' => []]);

    $stats = app(AdminStats::class);
    $until = CarbonImmutable::now()->addSecond();
    expect($stats->network($until->subDays(30), $until))->toBe(['posts' => 2, 'posts_period' => 1, 'active_authors' => 1, 'oases' => 1])
        ->and($stats->chat($until->subDays(30), $until)['identities'])->toBe(1)
        ->and($stats->weekly()->last()['posts'])->toBe(1)
        ->and($stats->members($until->subDays(30), $until)['registered'])->toBe(1);
});

it('publishes monthly transparency figures that replace the configured placeholders', function () {
    $admin = staffUser(Role::Admin);
    $this->actingAs($admin)->get(route('admin.transparency.index'))->assertOk()->assertSee('Noch nichts veröffentlicht.');

    $this->post(route('admin.transparency.store'), [
        'month' => '2026-09', 'members' => 1234, 'sponsored_seats' => 12, 'income' => '6170',
        'items' => [['label' => 'Server', 'amount' => '1000'], ['label' => 'Moderation', 'amount' => '4000'], ['label' => '', 'amount' => '']],
    ])->assertRedirect(route('admin.transparency.index'));

    $report = TransparencyReport::sole();
    expect($report->cost_items)->toBe(['Server' => 1000, 'Moderation' => 4000])->and($report->coverage())->toBe(123);

    auth()->logout();
    $this->get(route('oasis.transparenz'))->assertOk()
        ->assertSee('September 2026')->assertSee('1.234')->assertSee('123 %')->assertSee('Moderation');

    // Re-publishing the same month corrects it; future months and half-filled items are refused.
    $this->actingAs($admin)->post(route('admin.transparency.store'), ['month' => '2026-09', 'members' => 1, 'sponsored_seats' => 0, 'income' => 1, 'items' => [['label' => 'Server', 'amount' => 1]]]);
    expect(TransparencyReport::count())->toBe(1)->and(TransparencyReport::sole()->members)->toBe(1);
    $this->post(route('admin.transparency.store'), ['month' => now()->addMonth()->format('Y-m'), 'members' => 1, 'sponsored_seats' => 0, 'income' => 1, 'items' => [['label' => 'Server', 'amount' => 1]]])
        ->assertSessionHasErrors('month');
    $this->post(route('admin.transparency.store'), ['month' => '2026-08', 'members' => 1, 'sponsored_seats' => 0, 'income' => 1, 'items' => [['label' => 'Server', 'amount' => '']]])
        ->assertSessionHasErrors('items.0.amount');
    $this->post(route('admin.transparency.store'), ['month' => '2026-08', 'members' => 1, 'sponsored_seats' => 0, 'income' => 1, 'items' => [['label' => '', 'amount' => '']]])
        ->assertStatus(422);
});

it('lets admins create votes with at least two distinct answers', function () {
    $this->actingAs(staffUser(Role::Admin))->get(route('admin.votes.index'))->assertOk()->assertSee('Noch keine Abstimmungen.');
    $input = ['title' => 'Frage?', 'description' => 'Warum.', 'starts_at' => now()->addMinute()->format('Y-m-d\TH:i'), 'ends_at' => now()->addWeek()->format('Y-m-d\TH:i')];

    $this->post(route('admin.votes.store'), $input + ['options' => ['Ja', ' Ja ', '']])->assertSessionHasErrors('options');
    $this->post(route('admin.votes.store'), $input + ['options' => ['Ja', 'Nein', '']])->assertRedirect(route('admin.votes.index'));

    expect(Vote::sole()->options->pluck('label')->all())->toBe(['Ja', 'Nein']);
    $this->get(route('admin.votes.index'))->assertSee('geplant')->assertDontSee('Ja: ');
});

it('counts one secret ballot per member with an active membership', function () {
    $vote = openVote(staffUser(Role::Admin));
    $member = votingMember();
    [$first, $second] = $vote->options;

    $this->actingAs(N::member())->get(route('app.votes'))->assertOk()->assertSee('Abstimmen können Mitglieder mit aktiver Mitgliedschaft');
    $this->post(route('app.votes.store', $vote), ['option' => $first->id])->assertForbidden();

    $this->actingAs($member)->get(route('app.votes'))->assertSee('Stimme abgeben');
    $this->post(route('app.votes.store', $vote), ['option' => $first->id])->assertRedirect(route('app.votes'));
    $this->post(route('app.votes.store', $vote), ['option' => $second->id])->assertSessionHasErrors('option');
    $this->get(route('app.votes'))->assertSee('Du hast abgestimmt.');

    expect($first->fresh()->votes_count)->toBe(1)->and($second->fresh()->votes_count)->toBe(0)
        ->and(VoteParticipation::count())->toBe(1);
    // Secrecy: no stored row links the member to an option.
    expect(collect(DB::table('vote_participations')->first())->keys()->all())->toBe(['vote_id', 'user_id', 'voted_on']);
});

it('refuses foreign options and votes that are not running', function () {
    $admin = staffUser(Role::Admin);
    $vote = openVote($admin);
    $other = openVote($admin, ['A', 'B']);
    $this->actingAs(votingMember());

    $this->post(route('app.votes.store', $vote), ['option' => $other->options->first()->id])->assertSessionHasErrors('option');
    $vote->forceFill(['ends_at' => now()->subMinute()])->save();
    $this->post(route('app.votes.store', $vote), ['option' => $vote->options->first()->id])->assertSessionHasErrors('option');
});

it('publishes results only after the end, in the app and on the Transparenz page', function () {
    $vote = openVote(staffUser(Role::Admin));
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[0]->id);
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[0]->id);
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[1]->id);

    $this->get(route('oasis.transparenz'))->assertSee('Läuft')->assertSee('Wohin mit dem Überschuss?')->assertDontSee('67 %');

    $this->travel(8)->days();
    $this->get(route('oasis.transparenz'))->assertSee('Abgeschlossen')->assertSee('Mehr Patenplätze 67 %, Eine Rücklage 33 % (3 Stimmen)');
    $this->actingAs(N::member())->get(route('app.votes'))->assertSee('3 Stimmen')->assertSee('67 %');
    expect($vote->fresh()->load('options')->winners())->toBe(['Mehr Patenplätze']);
});

it('shows ended votes without ballots honestly', function () {
    $vote = openVote(staffUser(Role::Admin));
    $vote->forceFill(['ends_at' => now()->subMinute()])->save();

    $this->get(route('oasis.transparenz'))->assertSee('keine Stimmen abgegeben');
    expect($vote->fresh()->load('options')->winners())->toBe([]);
});
