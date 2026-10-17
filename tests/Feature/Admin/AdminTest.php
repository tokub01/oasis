<?php

use App\Actions\Governance\ManageVotes;
use App\Enums\Role;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Votes;
use App\Models\ChatIdentity;
use App\Models\GiftMembership;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteParticipation;
use App\Queries\Admin\AdminStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

/* Stage 6: admin access, statistics module and secret member votes. */
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
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Mitglieder und Beiträge')->assertSee('nur Metadaten');
    $this->get(route('admin.dashboard', ['tage' => 7]))->assertOk()->assertSee('letzten 7 Tage');
    // Periods outside the offered choices fall back to 30 days.
    $this->get(route('admin.dashboard', ['tage' => 3]))->assertOk()->assertSee('letzten 30 Tage');
    Livewire::test(Dashboard::class)->set('days', 90)->assertSee('letzten 90 Tage')->set('days', 5)->assertSet('days', 30);
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

it('lets admins create votes with at least two distinct answers', function () {
    $this->actingAs(staffUser(Role::Admin))->get(route('admin.votes.index'))->assertOk()->assertSeeLivewire(Votes::class)
        ->assertSee('Noch keine Abstimmungen.');
    $input = ['title' => 'Frage?', 'description' => 'Warum.', 'starts_at' => now()->addMinute()->format('Y-m-d\TH:i'), 'ends_at' => now()->addWeek()->format('Y-m-d\TH:i')];

    Livewire::test(Votes::class)->set($input + ['options' => ['Ja', ' Ja ', '']])->call('create')->assertHasErrors('options');
    expect(Vote::count())->toBe(0);
    Livewire::test(Votes::class)->set($input + ['options' => ['Ja', 'Nein', '']])->call('create')->assertHasNoErrors()
        ->assertSee('Die Abstimmung ist angelegt.')->assertSet('title', '')->assertSee('geplant')->assertDontSee('Ja: ');

    expect(Vote::sole()->options->pluck('label')->all())->toBe(['Ja', 'Nein']);
});

it('counts one secret ballot per member with an active membership', function () {
    $vote = openVote(staffUser(Role::Admin));
    $member = votingMember();
    [$first, $second] = $vote->options;

    $this->actingAs(N::member())->get(route('app.votes'))->assertOk()->assertSeeLivewire(App\Livewire\Governance\Votes::class)->assertSee('Abstimmen können Mitglieder mit aktiver Mitgliedschaft');
    Livewire::actingAs(N::member())->test(App\Livewire\Governance\Votes::class)
        ->set("options.{$vote->id}", $first->id)
        ->call('cast', $vote->id)
        ->assertForbidden();

    $this->actingAs($member)->get(route('app.votes'))->assertSee('Stimme abgeben');
    Livewire::actingAs($member)->test(App\Livewire\Governance\Votes::class)
        ->set("options.{$vote->id}", $first->id)
        ->call('cast', $vote->id)
        ->assertHasNoErrors()
        ->assertSee('Danke, deine Stimme ist gezählt.');
    Livewire::actingAs($member)->test(App\Livewire\Governance\Votes::class)
        ->set("options.{$vote->id}", $second->id)
        ->call('cast', $vote->id)
        ->assertHasErrors('option');
    $this->actingAs($member)->get(route('app.votes'))->assertSee('Du hast abgestimmt.');

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

    Livewire::test(App\Livewire\Governance\Votes::class)
        ->set("options.{$vote->id}", $other->options->first()->id)
        ->call('cast', $vote->id)
        ->assertHasErrors('option');
    $vote->forceFill(['ends_at' => now()->subMinute()])->save();
    Livewire::test(App\Livewire\Governance\Votes::class)
        ->set("options.{$vote->id}", $vote->options->first()->id)
        ->call('cast', $vote->id)
        ->assertHasErrors('option');
});

it('publishes results in the app only after the end', function () {
    $vote = openVote(staffUser(Role::Admin));
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[0]->id);
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[0]->id);
    app(ManageVotes::class)->cast($vote, votingMember(), $vote->options[1]->id);

    $member = N::member();
    $this->actingAs($member)->get(route('app.votes'))->assertSee('Wohin mit dem Überschuss?')->assertDontSee('67 %');

    $this->travel(8)->days();
    $this->actingAs($member)->get(route('app.votes'))->assertSee('3 Stimmen')->assertSee('67 %')->assertSee('33 %');
    expect($vote->fresh()->load('options')->winners())->toBe(['Mehr Patenplätze']);
});

it('shows ended votes without ballots honestly', function () {
    $vote = openVote(staffUser(Role::Admin));
    $vote->forceFill(['ends_at' => now()->subMinute()])->save();

    $this->actingAs(N::member())->get(route('app.votes'))->assertSee('0 Stimmen');
    expect($vote->fresh()->load('options')->winners())->toBe([]);
});
