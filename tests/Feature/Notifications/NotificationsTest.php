<?php

use App\Actions\Network\ManageFollows;
use App\Actions\Network\ManageInvitations;
use App\Actions\Network\ManageOasis;
use App\Actions\Network\PublishPost;
use App\Actions\Notifications\SendHint;
use App\Enums\Network\OasisRole;
use App\Enums\Notifications\NoticeKind;
use App\Events\Network\Followed;
use App\Events\Network\PostPublished;
use App\Livewire\Notifications\Inbox;
use App\Livewire\Notifications\Settings;
use App\Mail\Notifications\DailyDigest;
use App\Models\Block;
use App\Models\Mute;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Support\Network\Mentions;
use App\Support\Notifications\HintPresentation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as Fixtures;

// Integration tests use real network actions, Laravel database notifications and the independent Codex database.
beforeEach(function () {
    // Until Claude integrates search, only this test supplies the agreed route (never production registration).
    if (! Route::has('search.tags.show')) {
        Route::get('/themen/{tag}', fn () => 'Thema')->name('search.tags.show');
    }
});

it('parses only whole distinct usernames and never email addresses', function () {
    expect(Mentions::usernames('@Alice @ALICE (@bob_1) a@b.tld a@charlie.tld foo.bar@delta.tld @ab @'.str_repeat('x', 31).' @good_name'))
        ->toBe(['alice', 'bob_1', 'good_name']);
    expect(Mentions::usernames('ohne Erwähnung @ümlaut @abcä @@alice'))->toBe([]);
});

it('escapes rich text and links only existing mentions plus bounded unicode topics', function () {
    $member = Fixtures::member(['username' => 'alice']);
    $text = "<script>alert('x')</script> @ALICE @unknown a@alice.tld\n#Grün_42 #a #".str_repeat('a', 41);
    $html = Blade::render('<x-network.rich-text :text="$text" :author="$member" />', compact('text', 'member'));
    expect($html)->toContain('&lt;script&gt;', route('oasis.profil', 'alice'), route('search.tags.show', 'Grün_42'), '@unknown', "\n")
        ->not->toContain('<script>', route('oasis.profil', 'unknown'));
    expect(substr_count($html, '<a '))->toBe(2);
    expect(Blade::render('<x-network.rich-text text="plain &amp; text" />'))->toContain('plain &amp;amp; text');
});

it('notifies a new personal follow once and ignores oasis follows', function () {
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $follows = app(ManageFollows::class);
    $follows->follow($actor, $recipient);
    $follows->follow($actor, $recipient);
    event(new Followed($actor, $recipient));
    expect($recipient->notifications()->count())->toBe(1);
    expect($recipient->notifications()->first()->data)->toMatchArray(['kind' => 'follow', 'actor_id' => $actor->id, 'excerpt' => '']);
    $follows->unfollow($actor, $recipient);
    $follows->follow($actor, $recipient);
    expect($recipient->notifications()->count())->toBe(2);
    $follows->follow($actor, Fixtures::oasis($recipient));
    expect($recipient->notifications()->count())->toBe(2);
});

it('prioritizes reply over mention and sends carries and mentions with bounded payloads', function () {
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $other = Fixtures::member();
    $original = Fixtures::post($recipient);
    $post = app(PublishPost::class)->handle($actor, ['body' => '@'.$recipient->username.' @'.$other->username.' '.str_repeat('ü', 150), 'visibility' => 'public', 'reply_to' => $original]);
    event(new PostPublished($post));
    $hint = $recipient->notifications()->sole();
    expect($hint->data['kind'])->toBe('reply');
    expect(Str::length($hint->data['excerpt']))->toBe(120);
    expect(array_keys($hint->data))->toBe(['kind', 'actor_id', 'post_id', 'excerpt']);
    expect($other->notifications()->sole()->data['kind'])->toBe('mention');
    app(PublishPost::class)->handle($actor, ['body' => 'Weitergetragen', 'visibility' => 'public', 'carried' => $original]);
    expect($recipient->notifications()->where('data->kind', 'carry')->count())->toBe(1);
});

it('notifies at most ten mentioned accounts in text order', function () {
    $actor = Fixtures::member();
    $users = User::factory()->count(12)->create();
    $body = $users->map(fn ($user) => '@'.$user->username)->implode(' ');
    app(PublishPost::class)->handle($actor, ['body' => $body, 'visibility' => 'public']);
    foreach ($users as $index => $user) {
        expect($user->notifications()->count())->toBe($index < 10 ? 1 : 0);
    }
});

it('suppresses self hints, unreadable posts and disabled hint kinds', function () {
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $sender = app(SendHint::class);
    $sender->handle($actor, $actor, NoticeKind::Mention, 'self');
    $removed = Fixtures::post($actor, ['removed_from_oasis_at' => now()]);
    $sender->handle($recipient, $actor, NoticeKind::Mention, 'removed', $removed);
    $guestAccount = Fixtures::member(['email_verified_at' => null]);
    $private = Fixtures::post($actor, ['visibility' => 'members']);
    $sender->handle($guestAccount, $actor, NoticeKind::Mention, 'private', $private);
    NotificationPreference::create(['user_id' => $recipient->id, 'kinds' => ['mention' => false]]);
    $sender->handle($recipient, $actor, NoticeKind::Mention, 'off');
    expect(DatabaseNotification::count())->toBe(0);
});

it('suppresses real blocks in both directions and recipient mutes only', function () {
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $sender = app(SendHint::class);
    Block::create(['user_id' => $recipient->id, 'blocked_user_id' => $actor->id]);
    $sender->handle($recipient, $actor, NoticeKind::Follow, 'blocked');
    $sender->handle($actor, $recipient, NoticeKind::Follow, 'reverse');
    Block::query()->delete();
    $recipient->clearSafetyCache();
    $actor->clearSafetyCache();
    Mute::create(['user_id' => $recipient->id, 'muted_user_id' => $actor->id]);
    $recipient->clearSafetyCache();
    $sender->handle($recipient, $actor, NoticeKind::Follow, 'muted');
    $sender->handle($actor, $recipient, NoticeKind::Follow, 'one-way');
    expect($recipient->notifications()->count())->toBe(0);
    expect($actor->notifications()->count())->toBe(1);
});

it('adds invitation and host hints only for actual changes', function () {
    $host = Fixtures::member();
    $member = Fixtures::member();
    $invitations = app(ManageInvitations::class);
    $invitation = $invitations->create($host);
    expect($invitations->accept($invitation->token, $member))->toBeTrue();
    expect($invitations->accept($invitation->token, $member))->toBeFalse();
    expect($host->notifications()->where('data->kind', 'invitation')->count())->toBe(1);
    $oasis = Fixtures::oasis($host);
    app(ManageFollows::class)->follow($member, $oasis);
    $this->actingAs($host);
    app(ManageOasis::class)->setRole($oasis, $member, OasisRole::Host);
    app(ManageOasis::class)->setRole($oasis, $member, OasisRole::Host);
    expect($member->notifications()->where('data->kind', 'host')->count())->toBe(1);
});

it('shows calendar groups, pagination and owner-scoped read actions for read-only members', function () {
    $recipient = Fixtures::readOnlyMember();
    $actor = Fixtures::member();
    $this->travelTo(now()->startOfWeek()->addDays(3)->setTime(12, 0));
    foreach (range(1, 22) as $i) {
        app(SendHint::class)->handle($recipient, $actor, NoticeKind::Follow, 'inbox:'.$i);
    }
    $hints = $recipient->notifications()->get();
    $hints[0]->forceFill(['created_at' => now()->subDay()])->save();
    $hints[1]->forceFill(['created_at' => now()->subWeeks(2)])->save();
    $this->actingAs($recipient)->get(route('notifications.index'))->assertOk()->assertSeeLivewire(Inbox::class)
        ->assertSee('Heute')->assertSee('Ungelesen');
    Livewire::withQueryParams(['page' => 2])->test(Inbox::class)->assertSee('Diese Woche')->assertSee('Früher');
    Livewire::test(Inbox::class)->call('markRead', $hints[2]->id)->assertSee('Der Hinweis ist gelesen.');
    expect($hints[2]->fresh()->read_at)->not->toBeNull();
    $this->actingAs($actor);
    Livewire::test(Inbox::class)->call('markRead', $hints[3]->id)->assertStatus(404);
    expect($hints[3]->fresh()->read_at)->toBeNull();
    $this->actingAs($recipient);
    Livewire::test(Inbox::class)->call('markAllRead')->assertSee('Alle Hinweise sind gelesen.')->assertDontSee('Ungelesen');
    expect($recipient->unreadNotifications()->count())->toBe(0);
    $this->travelBack();
});

it('hides stale hints after content removal, actor deletion and safety changes', function () {
    $recipient = Fixtures::member();
    $actor = Fixtures::member();
    $post = Fixtures::post($actor);
    $sender = app(SendHint::class);
    $presentation = app(HintPresentation::class);
    $sender->handle($recipient, $actor, NoticeKind::Mention, 'stale', $post);
    $hint = $recipient->notifications()->sole();
    expect($presentation->forUser($hint, $recipient))->not->toBeNull();
    $post->forceFill(['removed_from_oasis_at' => now()])->save();
    expect($presentation->forUser($hint, $recipient))->toBeNull();
    $post->delete();
    expect($presentation->forUser($hint, $recipient))->toBeNull();
    $sender->handle($recipient, $actor, NoticeKind::Follow, 'stale-follow');
    $follow = $recipient->notifications()->where('data->kind', 'follow')->sole();
    Mute::create(['user_id' => $recipient->id, 'muted_user_id' => $actor->id]);
    $recipient->clearSafetyCache();
    expect($presentation->forUser($follow, $recipient))->toBeNull();
    $this->actingAs($recipient)->get(route('notifications.index'))->assertOk()->assertDontSee($actor->display_name);
    $actor->delete();
    expect($presentation->forUser($follow, $recipient))->toBeNull();
});

it('stores six independent preferences and keeps email opt-in off by default', function () {
    $user = Fixtures::member();
    $this->actingAs($user)->get(route('notifications.settings'))->assertOk()->assertSeeLivewire(Settings::class)->assertSee('Hinweise einstellen');
    expect(NotificationPreference::count())->toBe(0);
    Livewire::test(Settings::class)->set('kinds.follow', true)->set('kinds.mention', false)->set('digest', true)
        ->call('save')->assertHasNoErrors()->assertSee('Deine Hinweis-Einstellungen sind gespeichert.');
    $preference = NotificationPreference::where('user_id', $user->id)->sole();
    expect($preference->enabled(NoticeKind::Follow))->toBeTrue();
    expect($preference->enabled(NoticeKind::Mention))->toBeFalse();
    expect($preference->digest_enabled)->toBeTrue();
    expect($preference->kinds)->toHaveCount(6);
    Livewire::test(Settings::class)->set('kinds.follow', 'invalid')->call('save')->assertHasErrors('kinds.follow');
    Livewire::test(Settings::class)->set('digest', false)->set('kinds.unknown', true)->call('save')->assertHasNoErrors();
    expect($preference->fresh()->digest_enabled)->toBeFalse()->and($preference->fresh()->kinds)->not->toHaveKey('unknown');
});

it('requires authentication and verification on every notification page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->get(route('notifications.settings'))->assertRedirect(route('login'));
    $user = Fixtures::member(['email_verified_at' => null]);
    $this->actingAs($user)->get(route('notifications.index'))->assertRedirect(route('verification.notice'));
});

it('sends only one opted-in daily email with currently visible unread hints', function () {
    Mail::fake();
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $default = Fixtures::member();
    NotificationPreference::create(['user_id' => $recipient->id, 'digest_enabled' => true]);
    $sender = app(SendHint::class);
    $sender->handle($recipient, $actor, NoticeKind::Mention, 'digest', Fixtures::post($actor));
    $sender->handle($default, $actor, NoticeKind::Follow, 'default-off');
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertSent(DailyDigest::class, fn ($mail) => $mail->hasTo($recipient->email) && $mail->count === 1);
    Mail::assertSentCount(1);
    expect($recipient->unreadNotifications()->count())->toBe(1);
    $rendered = (new DailyDigest(1))->render();
    expect($rendered)->toContain('einen neuen ungelesenen Hinweis', route('notifications.index'), route('notifications.settings'));
    $this->travel(1)->days();
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertSentCount(1);
    $this->travelBack();
});

it('skips unverified and safety-hidden digests and retries mail failures', function () {
    Mail::fake();
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    $unverified = Fixtures::member(['email_verified_at' => null]);
    foreach ([$recipient, $unverified] as $user) {
        NotificationPreference::create(['user_id' => $user->id, 'digest_enabled' => true]);
        app(SendHint::class)->handle($user, $actor, NoticeKind::Follow, 'hidden:'.$user->id);
    }
    Mute::create(['user_id' => $recipient->id, 'muted_user_id' => $actor->id]);
    $recipient->clearSafetyCache();
    $this->artisan('oasis:notification-digest')->assertSuccessful();
    Mail::assertNothingSent();
    Mute::query()->delete();
    NotificationPreference::where('user_id', $recipient->id)->update(['last_digest_at' => null]);
    Mail::shouldReceive('to')->once()->with($recipient->email)->andThrow(new RuntimeException('transport failed'));
    expect(fn () => $this->artisan('oasis:notification-digest')->run())->toThrow(RuntimeException::class, 'transport failed');
    expect(NotificationPreference::where('user_id', $recipient->id)->sole()->last_digest_at)->toBeNull();
});

it('prunes only network hints older than 90 days and erases account hint storage on deletion', function () {
    $actor = Fixtures::member();
    $recipient = Fixtures::member();
    foreach (['old', 'boundary', 'recent'] as $key) {
        app(SendHint::class)->handle($recipient, $actor, NoticeKind::Follow, $key);
    }
    $hints = $recipient->notifications()->get();
    $hints[0]->forceFill(['created_at' => now()->subDays(91)])->save();
    $hints[1]->forceFill(['created_at' => now()->subDays(90)->addMinute()])->save();
    $external = $recipient->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'OtherNotification', 'data' => [], 'created_at' => now()->subDays(100)]);
    $this->artisan('oasis:prune-notifications')->expectsOutput('1 alte Hinweise gelöscht.')->assertSuccessful();
    expect($recipient->notifications()->count())->toBe(3);
    expect($external->fresh())->not->toBeNull();
    NotificationPreference::create(['user_id' => $recipient->id]);
    $recipient->delete();
    expect(DatabaseNotification::count())->toBe(0);
    expect(NotificationPreference::count())->toBe(0);
});

it('registers notification jobs with the required digest time and timezone', function () {
    $events = app(Schedule::class)->events();
    $digest = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'oasis:notification-digest'));
    $prune = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'oasis:prune-notifications'));
    expect($digest)->not->toBeNull();
    expect($digest->expression)->toBe('0 18 * * *');
    expect($digest->timezone)->toBe('Europe/Berlin');
    expect($prune)->not->toBeNull();
});

it('links host hints to their oasis and hides them after the oasis is deleted', function () {
    $host = Fixtures::member();
    $member = Fixtures::member();
    $oasis = Fixtures::oasis($host);
    app(ManageFollows::class)->follow($member, $oasis);
    $this->actingAs($host);
    app(ManageOasis::class)->setRole($oasis, $member, OasisRole::Host);
    $hint = $member->notifications()->where('data->kind', 'host')->sole();
    $presentation = app(HintPresentation::class);
    expect($presentation->forUser($hint, $member)['url'])->toBe(route('network.oases.show', $oasis));
    $this->actingAs($member)->get(route('notifications.index'))->assertOk()->assertSee('Du wirst Gastgeber:in einer Oase');
    $oasis->delete();
    expect($presentation->forUser($hint, $member))->toBeNull();
    expect($presentation->forUser(new DatabaseNotification(['data' => ['kind' => 'unknown']]), $member))->toBeNull();
});

it('skips empty mention lookups and caches hits and misses for this request only', function () {
    $author = Fixtures::member(['username' => 'alice']);
    $author->blockedUserIds();
    DB::enableQueryLog();
    DB::flushQueryLog();
    Blade::render('<x-network.rich-text text="Ohne Erwähnung #Garten" />');
    expect(DB::getQueryLog())->toBeEmpty();
    foreach (range(1, 20) as $i) {
        Blade::render('<x-network.rich-text text="@ALICE @noch_nicht" :author="$author" />', compact('author'));
    }
    expect(DB::getQueryLog())->toHaveCount(4);
    expect(Mentions::existing(['ALICE', 'noch_nicht']))->toBe(['alice']);
    expect(DB::getQueryLog())->toHaveCount(4);
    DB::disableQueryLog();

    // A newly created account becomes linkable in the next real HTTP request, despite the previous cached miss.
    Fixtures::member(['username' => 'noch_nicht']);
    $post = Fixtures::post(Fixtures::member(), ['body' => '@noch_nicht']);
    $this->actingAs(Fixtures::member())->get(route('network.posts.show', $post))
        ->assertOk()->assertSee('href="'.route('oasis.profil', 'noch_nicht').'"', false);
});

it('loads resources for twenty hints in three queries rather than per hint', function () {
    $viewer = Fixtures::member();
    $oasis = Fixtures::oasis(Fixtures::member());
    foreach (range(1, 20) as $i) {
        $actor = Fixtures::member();
        $post = $i <= 10 ? Fixtures::post($actor) : null;
        app(SendHint::class)->handle($viewer, $actor, $i <= 10 ? NoticeKind::Mention : NoticeKind::Host,
            'batch:'.$i, $post, $i <= 10 ? [] : ['oasis_id' => $oasis->id]);
    }
    $hints = $viewer->notifications()->get();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $items = app(HintPresentation::class)->forMany($hints, $viewer);
    expect($items)->toHaveCount(20);
    expect(DB::getQueryLog())->toHaveCount(3);
    DB::flushQueryLog();
    expect(app(HintPresentation::class)->forMany([], $viewer))->toBe([]);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('keeps plain pagination links even if an earlier Livewire component changed the default view', function () {
    $recipient = Fixtures::member();
    $actor = Fixtures::member();
    foreach (range(1, 21) as $i) {
        app(SendHint::class)->handle($recipient, $actor, NoticeKind::Follow, 'plain-pagination:'.$i);
    }
    $this->actingAs($recipient);
    Livewire::test(Inbox::class)->assertViewHas('hints', fn ($hints) => $hints->currentPage() === 1 && $hints->count() === 20)
        ->call('nextPage')->assertViewHas('hints', fn ($hints) => $hints->currentPage() === 2 && $hints->count() === 1);
});
