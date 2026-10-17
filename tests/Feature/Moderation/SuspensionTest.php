<?php

/* Kontosperren (docs/oasis-moderation.md, "Sperren"): instant provisional lock, statement, release, strikes, ban. */

use App\Actions\Moderation\ChangeRole;
use App\Actions\Moderation\DecideCase;
use App\Ai\Agents\ContentCheck as ContentCheckAgent;
use App\Enums\Role;
use App\Livewire\Account\Suspended;
use App\Livewire\Moderation\Suspensions;
use App\Mail\Moderation\AccountReleasedNotice;
use App\Mail\Moderation\AccountSuspendedNotice;
use App\Models\AccountStrike;
use App\Models\AccountSuspension;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
    config(['oasis.moderation.enabled' => true]);
});

function lockVerdict(float $insult = 0.95): array
{
    return ['insult' => $insult, 'hate' => 0, 'threat' => 0, 'extremism' => 0, 'harassment' => 0, 'sexual' => 0,
        'sexual_minors' => 0, 'reason' => 'Grobe Beleidigung einer Person.'];
}

function lockStaff(Role $role = Role::Moderator, array $attributes = []): User
{
    return User::factory()->create(['role' => $role, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()] + $attributes);
}

/** Publish an insult as $author (tone confirmed) and return the case once the background check opened it. */
function insultBy(User $author, string $body = 'Du bist ein Vollidiot'): ModerationCase
{
    Cache::flush();
    ContentCheckAgent::fake([lockVerdict()]);
    NetworkActions::publish(['body' => $body, 'visibility' => 'members', 'tone_confirmed' => '1'], $author);

    return ModerationCase::latest('id')->firstOrFail();
}

function decide(ModerationCase $case, string $outcome): void
{
    app(DecideCase::class)->handle($case, lockStaff(), ['outcome' => $outcome, 'ground' => 'community_rules',
        'rule_reference' => 'Regel 1', 'facts' => 'Beleidigung eines Mitglieds.', 'explanation' => 'Verstoß gegen Regel 1.']);
}

it('locks the account the moment a Tropfen is held and explains the accusation', function () {
    $author = N::member();
    $case = insultBy($author);

    $lock = $author->activeSuspension();
    expect($lock->type)->toBe(AccountSuspension::PROVISIONAL)->and($lock->moderation_case_id)->toBe($case->id)
        ->and($lock->accusation)->toContain('Verdacht auf eine Beleidigung')->toContain('Grobe Beleidigung einer Person.')
        ->and($lock->excerpt)->toBe('Du bist ein Vollidiot')
        ->and($author->fresh()->canParticipate())->toBeFalse();
    Mail::assertSent(AccountSuspendedNotice::class, fn ($mail) => $mail->hasTo($author->email) && str_contains($mail->render(), 'Bestätigte Verstöße bisher: 0 von 3'));

    $this->actingAs($author)->get(route('app.home'))->assertRedirect(route('app.suspended'));
    $this->get(route('app.suspended'))->assertOk()->assertSee('Dein Konto ist vorübergehend gesperrt')->assertSee('Du bist ein Vollidiot');
    $this->get(route('app.settings'))->assertOk();
    $this->get(route('account.export'))->assertOk();
    $this->get(route('app.membership'))->assertOk();
});

it('lets the locked member answer, and staff read it and release the account', function () {
    $author = N::member();
    insultBy($author);
    Livewire::actingAs($author)->test(Suspended::class)->set('statement', 'kurz')->call('save')->assertHasErrors('statement')
        ->set('statement', 'Es tut mir leid, ich war wütend. Kommt nicht wieder vor.')->call('save')->assertHasNoErrors();
    expect($author->activeSuspension()->statement)->toBe('Es tut mir leid, ich war wütend. Kommt nicht wieder vor.');

    $moderator = lockStaff();
    Livewire::actingAs($moderator)->test(Suspensions::class)->assertSee('Es tut mir leid, ich war wütend.')
        ->call('release', $author->activeSuspension()->id)->assertSet('status', fn ($s) => str_contains($s, 'wieder freigegeben'));

    expect($author->fresh()->isSuspended())->toBeFalse()->and($author->activeStrikeCount())->toBe(0);
    Mail::assertSent(AccountReleasedNotice::class);
    $this->actingAs($author)->get(route('app.home'))->assertOk();
});

it('counts a confirmed violation, releases the provisional lock, and bans at the third', function () {
    $author = N::member();
    foreach ([1, 2] as $round) {
        decide(insultBy($author, 'Beleidigung Nummer '.$round), 'content_removed');
        expect($author->fresh()->isSuspended())->toBeFalse()->and($author->activeStrikeCount())->toBe($round);
    }

    decide(insultBy($author, 'Beleidigung Nummer 3'), 'content_removed');

    $lock = $author->activeSuspension();
    expect($lock->type)->toBe(AccountSuspension::BAN)->and($author->activeStrikeCount())->toBe(3);
    Mail::assertSent(AccountSuspendedNotice::class, fn ($mail) => $mail->suspension->type === AccountSuspension::BAN);
    $this->actingAs($author)->get(route('app.home'))->assertRedirect(route('app.suspended'));

    // Only admins lift a ban; moderators may not.
    Livewire::actingAs(lockStaff())->test(Suspensions::class)->call('release', $lock->id)->assertForbidden();
    expect($author->fresh()->isSuspended())->toBeTrue();
});

it('releases without a strike when the moderation team finds no violation', function () {
    $author = N::member();
    decide(insultBy($author), 'no_action');

    expect($author->fresh()->isSuspended())->toBeFalse()->and($author->activeStrikeCount())->toBe(0)
        ->and(Post::sole()->held_at)->toBeNull();
});

it('keeps the account locked after a decision "Konto gesperrt" until staff release it', function () {
    $author = N::member();
    decide(insultBy($author), 'account_suspended');

    expect($author->activeSuspension()->type)->toBe(AccountSuspension::SUSPENSION)->and(AccountStrike::count())->toBe(1);
});

it('locks moderators and admins too, and holds but never locks the operator account', function () {
    $moderator = lockStaff();
    insultBy($moderator);
    expect($moderator->fresh()->isSuspended())->toBeTrue();
    $this->actingAs($moderator)->get(route('moderation.index'))->assertRedirect(route('app.suspended'));

    // The operator account: held and reported like everyone, strikes counted, but never locked or banned.
    $operator = lockStaff(Role::Admin, ['email' => 'info@velwebsolutions.de']);
    foreach ([1, 2, 3] as $round) {
        $case = insultBy($operator, 'Betreiber schreibt derb '.$round);
        expect(Post::latest('id')->first()->held_at)->not->toBeNull();
        decide($case, $round === 3 ? 'account_suspended' : 'content_removed');
    }
    expect($operator->fresh()->isSuspended())->toBeFalse()->and($operator->activeStrikeCount())->toBe(3);
});

it('lets only the operator account make someone an admin', function () {
    $admin = lockStaff(Role::Admin);
    $operator = lockStaff(Role::Admin, ['email' => 'info@velwebsolutions.de']);
    $member = N::member();

    expect(fn () => app(ChangeRole::class)->handle($member, Role::Admin, $admin))->toThrow(Exception::class);
    app(ChangeRole::class)->handle($member, Role::Moderator, $admin);
    expect($member->fresh()->role)->toBe(Role::Moderator);
    expect(fn () => app(ChangeRole::class)->handle($operator, Role::Member, $admin))->toThrow(Exception::class);

    app(ChangeRole::class)->handle($member, Role::Admin, $operator);
    expect($member->fresh()->role)->toBe(Role::Admin);
    expect(fn () => app(ChangeRole::class)->handle($member, Role::Member, $admin))->toThrow(Exception::class);
});
