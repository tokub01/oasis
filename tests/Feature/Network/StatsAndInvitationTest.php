<?php

use App\Actions\Network\ManageFollows;
use App\Actions\Network\ManageInvitations;
use App\Models\Invitation;
use App\Models\Reaction;
use App\Models\User;
use App\Queries\Network\MemberStats;
use App\Queries\Network\OasisStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\NetworkFixtures as N;

/* "Deine Zahlen", host statistics of an oasis and invitation links. */
beforeEach(fn () => $this->withoutVite());

it('counts a member’s own numbers and never lists who reacted', function () {
    $me = N::member();
    $fan = N::member();
    $post = N::post($me, ['body' => 'Viel beachtet']);
    Reaction::create(['post_id' => $post->id, 'user_id' => $fan->id]);
    N::post($fan, ['reply_to_id' => $post->id]);
    N::post($fan, ['carried_post_id' => $post->id]);
    N::post($me, ['reply_to_id' => $post->id]); // own replies do not count as received
    app(ManageFollows::class)->follow($fan, $me);

    expect(app(MemberStats::class)->totals($me))->toBe([
        'posts' => 2, 'reactions' => 1, 'replies' => 1, 'carries' => 1, 'followers' => 1, 'following' => 0,
    ]);
    $this->actingAs($me)->get(route('network.stats'))->assertOk()
        ->assertSee('In deiner Karawane')->assertSee('Viel beachtet')->assertDontSee($fan->display_name);
});

it('groups weekly numbers by calendar week, oldest first', function () {
    $me = N::member();
    $now = CarbonImmutable::parse('2026-10-14 12:00');
    $this->travelTo($now->subWeeks(2));
    N::post($me);
    $this->travelTo($now);
    N::post($me);
    N::post($me);

    $weeks = app(MemberStats::class)->weekly($me, $now);
    expect($weeks)->toHaveCount(MemberStats::WEEKS)
        ->and($weeks->last()['posts'])->toBe(2)
        ->and($weeks[MemberStats::WEEKS - 3]['posts'])->toBe(1)
        ->and($weeks->first()['week']->isMonday())->toBeTrue();
});

it('shows oasis statistics to hosts only, as sums', function () {
    $host = N::member();
    $oasis = N::oasis($host);
    $member = N::member();
    app(ManageFollows::class)->follow($member, $oasis);
    N::post($member, ['oasis_id' => $oasis->id]);
    N::post($member, ['oasis_id' => $oasis->id, 'removed_from_oasis_at' => now()]);

    expect(app(OasisStats::class)->totals($oasis))->toBe([
        'members' => 2, 'hosts' => 1, 'posts' => 2, 'posts_30_days' => 2, 'active_authors_30_days' => 1, 'removed' => 1,
    ]);
    expect(app(OasisStats::class)->weekly($oasis)->last())->toMatchArray(['joined' => 2, 'posts' => 2]);

    $this->actingAs($member)->get(route('network.oases.stats', $oasis))->assertForbidden();
    $this->actingAs($host)->get(route('network.oases.stats', $oasis))->assertOk()
        ->assertSee('Nur für Gastgeber:innen')->assertDontSee($member->display_name);
});

it('creates invitation links within the monthly budget', function () {
    config(['oasis.invites_per_month' => 2]);
    $me = N::member();
    $this->actingAs($me);

    $this->post(route('network.invitations.store'))->assertRedirect();
    $this->post(route('network.invitations.store'));
    $this->post(route('network.invitations.store'))->assertSessionHasErrors('invitation');
    expect(Invitation::count())->toBe(2);
    $this->get(route('network.invitations.index'))->assertSee('diesen Monat noch 0')->assertSee(Invitation::first()->token);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()); // new month, trial still running
    $this->post(route('network.invitations.store'));
    expect(Invitation::count())->toBe(3);
});

it('connects inviter and new member after registering through a link', function () {
    Notification::fake();
    RateLimiter::clear('registration|127.0.0.1');
    $inviter = N::member();
    $invitation = app(ManageInvitations::class)->create($inviter);

    $this->get(route('network.invitations.show', $invitation->token))->assertOk()
        ->assertSee($inviter->display_name.' lädt dich zu Oasis ein.');
    $this->post(route('register'), [
        'display_name' => 'Neu Hier', 'username' => 'neu_hier', 'email' => 'neu@example.tld', 'birth_date' => '1990-06-15',
        'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'terms' => '1',
    ])->assertRedirect('/app');

    $newcomer = User::where('username', 'neu_hier')->sole();
    expect($newcomer->follows($inviter))->toBeTrue()
        ->and($inviter->follows($newcomer))->toBeTrue()
        ->and($invitation->fresh()->accepted_by)->toBe($newcomer->id);
});

it('shows a neutral page for used or expired links and does not remember them', function () {
    $invitation = app(ManageInvitations::class)->create(N::member());
    $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->get(route('network.invitations.show', $invitation->token))->assertOk()
        ->assertSee('Dieser Einladungslink gilt nicht mehr.')->assertSessionMissing('oasis.invitation');
    expect(app(ManageInvitations::class)->accept($invitation->token, N::member()))->toBeFalse();
});

it('lets read-only accounts see but not create invitation links', function () {
    $this->actingAs(N::readOnlyMember())->get(route('network.invitations.index'))->assertOk()->assertDontSee('Neuen Einladungslink erstellen');
    $this->post(route('network.invitations.store'))->assertForbidden();
});
