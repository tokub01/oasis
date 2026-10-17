<?php

use App\Actions\Moderation\DecideCase;
use App\Ai\Agents\ContentCheck as ContentCheckAgent;
use App\Enums\Role;
use App\Jobs\Moderation\CheckContent;
use App\Mail\Moderation\ContentHeldNotice;
use App\Mail\Moderation\DecisionNotice;
use App\Models\ContentCheck;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/* Stage 10: automated content check (Laravel AI SDK → Mistral, faked here), nudge, reporting, holding, release. */
beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
    // phpunit.xml switches the AI check off for all other tests; here it runs against a faked agent.
    config(['oasis.moderation.enabled' => true]);
});

/** Structured agent output with all categories at 0 except the given ones. */
function verdict(array $scores = [], string $reason = ''): array
{
    return $scores + ['insult' => 0, 'hate' => 0, 'threat' => 0, 'sexual' => 0, 'sexual_minors' => 0, 'reason' => $reason];
}

function moderator(): User
{
    return User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);
}

it('publishes clean Tropfen without a case and records the check', function () {
    ContentCheckAgent::fake([verdict()]);
    NetworkActions::publish(['body' => 'Schöner Tag heute', 'visibility' => 'public'], N::member())->assertHasNoErrors();

    $check = ContentCheck::sole();
    expect($check->status)->toBe('checked')->and($check->flagged)->toBeFalse()->and($check->source)->toBe('ai')
        ->and(ModerationCase::count())->toBe(0);
});

it('asks "Nochmal lesen?" before an insulting Tropfen and reports it when sent anyway', function () {
    ContentCheckAgent::fake([verdict(['insult' => 0.9], 'Direkte Beleidigung einer Person.')]);
    $me = N::member();

    NetworkActions::publish(['body' => 'Du bist ein Idiot', 'visibility' => 'public'], $me)->assertHasErrors('body')->assertSet('toneNudge', true);
    expect(Post::count())->toBe(0);

    NetworkActions::publish(['body' => 'Du bist ein Idiot', 'visibility' => 'public', 'tone_confirmed' => '1'])->assertHasNoErrors();
    $post = Post::sole();
    $case = ModerationCase::sole();

    expect($case->source)->toBeInstanceOf(ContentCheck::class)
        ->and($case->subject_user_id)->toBe($me->id)
        ->and($case->subject_url)->toBe(route('network.posts.show', $post))
        ->and($case->priority->value)->toBe('normal')
        ->and($post->fresh()->held_at)->toBeNull();
    ContentCheckAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '<text>Du bist ein Idiot</text>')
        && ! str_contains($prompt->prompt, $me->username) && ! str_contains($prompt->prompt, $me->email));
});

it('holds clearly sexual Tropfen for review, tells the author and lets only author and staff see them', function () {
    ContentCheckAgent::fake([verdict(['sexual' => 0.95], 'Sexuell expliziter Inhalt.')]);
    $author = N::member();
    NetworkActions::publish(['body' => 'expliziter Text', 'visibility' => 'public'], $author);
    $post = Post::sole();

    expect($post->held_at)->not->toBeNull()->and(ModerationCase::sole()->priority->value)->toBe('urgent');
    Mail::assertSent(ContentHeldNotice::class, fn ($mail) => $mail->hasTo($author->email));

    $this->get(route('network.posts.show', $post))->assertOk()->assertSee('möglicherweise sexuelle Inhalte');
    $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertNotFound();
    $this->actingAs(N::member())->get(route('app.home'))->assertDontSee('expliziter Text');
    auth()->logout();
    $this->get(route('network.posts.show', $post))->assertNotFound();
    $this->actingAs(moderator())->get(route('network.posts.show', $post))->assertOk();
});

it('makes a held Tropfen visible again when a human decides "no action", and says so in the decision mail', function () {
    ContentCheckAgent::fake([verdict(['sexual' => 0.95])]);
    NetworkActions::publish(['body' => 'Aktzeichnung im Museum', 'visibility' => 'public'], N::member());
    $case = ModerationCase::sole();
    $staff = moderator();

    app(DecideCase::class)->handle($case, $staff, [
        'outcome' => 'no_action', 'ground' => 'community_rules', 'rule_reference' => 'Regel 3',
        'facts' => 'Kunstbeschreibung.', 'explanation' => 'Kein Verstoß.',
    ]);

    expect(Post::sole()->held_at)->toBeNull();
    Mail::assertSent(DecisionNotice::class, fn ($mail) => str_contains($mail->render(), 'automatisierten Verfahren (Sprachmodell) erkannt'));
});

it('shows the automated detection with scores and reason in the moderation case', function () {
    ContentCheckAgent::fake([verdict(['threat' => 0.8], 'Androhung von Gewalt.')]);
    NetworkActions::publish(['body' => 'Ich finde dich', 'visibility' => 'public', 'tone_confirmed' => '1'], N::member());
    $case = ModerationCase::sole();

    $this->actingAs(moderator())->get(route('moderation.cases.show', $case))->assertOk()
        ->assertSee('Automatische Erkennung')->assertSee('Drohung: 80 %')->assertSee('Androhung von Gewalt.')->assertSee('Ich finde dich');
    expect($case->priority->value)->toBe('urgent');
});

it('never blocks posting when the model fails: the word list nudges and the job retries strictly', function () {
    ContentCheckAgent::fake(fn () => throw new RuntimeException('Mistral down'));
    $this->actingAs(N::member());

    NetworkActions::publish(['body' => 'Du Arschloch', 'visibility' => 'public'])->assertHasErrors('body');
    NetworkActions::publish(['body' => 'Ganz normaler Satz', 'visibility' => 'public'])->assertHasNoErrors();
    // The job could not reach the model; with a synchronous queue its failure handler lets the word list decide.
    expect(Post::count())->toBe(1)->and(ContentCheck::sole()->source)->toBe('wordlist');
});

it('lets the word list decide after the last retry so nothing stays unchecked', function () {
    $post = N::post(N::member(), ['body' => 'Du Wichser']);
    $check = ContentCheck::create(['checkable_type' => $post->getMorphClass(), 'checkable_id' => $post->id]);

    (new CheckContent($check))->failed(new RuntimeException('Mistral down'));

    expect($check->fresh()->status)->toBe('checked')->and($check->fresh()->source)->toBe('wordlist')
        ->and(ModerationCase::sole()->source->is($check))->toBeTrue();
});

it('checks profile and oasis texts when they change', function () {
    ContentCheckAgent::fake([verdict(['hate' => 0.9], 'Hetze.'), verdict()]);
    $me = N::member();
    NetworkActions::saveProfile(['bio' => 'hetzerischer Text', 'avatar_color' => 'sun'], $me);

    $case = ModerationCase::sole();
    expect($case->subject_url)->toBe(route('oasis.profil', $me->username))->and($case->subject_user_id)->toBe($me->id);

    N::oasis($me, ['name' => 'Ruhige Runde']);
    expect(ContentCheck::count())->toBe(2)->and(ModerationCase::count())->toBe(1);
});

it('re-queues unchecked Tropfen in the sweep', function () {
    ContentCheckAgent::fake([verdict()]);
    $post = N::post(N::member(), ['body' => 'Ohne Prüfung angelegt']);
    expect(ContentCheck::count())->toBe(0);

    $this->artisan('oasis:moderation-sweep')->assertSuccessful();

    expect(ContentCheck::where('checkable_id', $post->id)->sole()->status)->toBe('checked');
});

it('uses only the word list when the AI check is switched off', function () {
    config(['oasis.moderation.enabled' => false]);
    ContentCheckAgent::fake();
    NetworkActions::publish(['body' => 'Friedlich', 'visibility' => 'public'], N::member());

    ContentCheckAgent::assertNeverPrompted();
    expect(ContentCheck::sole()->source)->toBe('wordlist');
});
