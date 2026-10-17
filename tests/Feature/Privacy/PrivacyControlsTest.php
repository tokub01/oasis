<?php

/*
 * The further privacy controls (docs/oasis-privacy.md): who sees location and oases, limiting past Tropfen, hidden
 * words, restricting accounts, devices with login alerts. Every rule is checked where it is enforced, not in the form.
 */

use App\Actions\Network\PublishPost;
use App\Actions\Privacy\UpdateHiddenWords;
use App\Actions\Safety\ManageRestriction;
use App\Enums\Notifications\NoticeKind;
use App\Livewire\Network\PostShow;
use App\Livewire\Network\ProfileShow;
use App\Livewire\Network\Source;
use App\Livewire\Privacy\Center;
use App\Livewire\Privacy\Devices;
use App\Livewire\Privacy\HiddenWords;
use App\Livewire\Safety\SafetyOverview;
use App\Mail\Privacy\LoginAlert;
use App\Models\Follow;
use App\Models\KnownDevice;
use App\Models\Post;
use App\Models\User;
use App\Support\Privacy\DeviceLabel;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

/** A reply of $author under $origin, published like in the app (hints included). */
function replyTo(Post $origin, User $author, string $body): Post
{
    return app(PublishPost::class)->handle($author, ['body' => $body, 'visibility' => 'members', 'reply_to' => $origin]);
}

function followEachOther(User $a, User $b): void
{
    Follow::create(['follower_id' => $a->id, 'followable_type' => $b->getMorphClass(), 'followable_id' => $b->id]);
}

describe('location and oases on the profile', function () {
    it('shows each only to its audience, and always to the owner', function (string $audience, bool $guest, bool $member, bool $followed) {
        $owner = N::member();
        $owner->profile()->create(['location' => 'Leipzig']);
        $owner->privacySetting()->create(['profile_audience' => 'everyone', 'location_audience' => $audience, 'oases_audience' => $audience]);
        N::oasis($owner, ['name' => 'Analoges Licht']);
        $friend = N::member();
        followEachOther($owner, $friend);

        $this->get(route('oasis.profil', $owner->username))->assertOk()
            ->{$guest ? 'assertSee' : 'assertDontSee'}('Leipzig')->{$guest ? 'assertSee' : 'assertDontSee'}('Analoges Licht');
        foreach ([[N::member(), $member], [$friend, $followed], [$owner, true]] as [$viewer, $visible]) {
            $html = $this->actingAs($viewer)->get(route('oasis.profil', $owner->username))->assertOk()->getContent();
            expect(str_contains($html, 'Leipzig'))->toBe($visible)->and(str_contains($html, 'Analoges Licht'))->toBe($visible);
        }
    })->with([
        'everyone' => ['everyone', true, true, true],
        'members' => ['members', false, true, true],
        'following' => ['following', false, false, true],
        'nobody' => ['nobody', false, false, false],
    ]);

    it('keeps location and oases members-only by default', function () {
        $owner = N::member();
        $owner->profile()->create(['location' => 'Leipzig']);
        $owner->privacySetting()->create(['profile_audience' => 'everyone']);

        $this->get(route('oasis.profil', $owner->username))->assertOk()->assertDontSee('Leipzig');
        $this->actingAs(N::member())->get(route('oasis.profil', $owner->username))->assertSee('Leipzig');
    });
});

describe('limiting past Tropfen', function () {
    it('makes only the own public Tropfen members-only, so guests lose access', function () {
        $me = N::member();
        $public = N::post($me, ['body' => 'Früher öffentlich']);
        $members = N::post($me, ['body' => 'Schon intern', 'visibility' => 'members']);
        $foreign = N::post(N::member(), ['body' => 'Fremd und öffentlich']);

        Livewire::actingAs($me)->test(Center::class)->assertSee('Betrifft 1 Tropfen.')
            ->call('limitPastPosts')->assertSet('status', 'Ein Tropfen ist jetzt nur noch für Mitglieder sichtbar.')
            ->assertSee('Du hast gerade keine öffentlichen Tropfen.');

        expect($public->fresh()->visibility->value)->toBe('members')
            ->and($members->fresh()->visibility->value)->toBe('members')
            ->and($foreign->fresh()->visibility->value)->toBe('public');
        auth()->logout();
        $this->get(route('network.posts.show', $public))->assertNotFound();
        $this->get(route('network.posts.show', $foreign))->assertOk();
    });
});

describe('hidden words', function () {
    it('stores words lower case and refuses empty, too long, duplicate and too many words', function () {
        $me = N::member();
        $words = app(UpdateHiddenWords::class);
        $words->add($me, '  Spoiler ');
        expect($me->fresh()->privacy()->hiddenWords())->toBe(['spoiler']);

        foreach (['', 'a', str_repeat('x', 41), 'SPOILER'] as $invalid) {
            expect(fn () => $words->add($me->fresh(), $invalid))->toThrow(ValidationException::class);
        }
        $me->privacySetting()->update(['hidden_words' => array_map(fn ($i) => 'wort'.$i, range(1, UpdateHiddenWords::MAX))]);
        expect(fn () => $words->add($me->fresh(), 'eins zu viel'))->toThrow(ValidationException::class);
    });

    it('adds and removes words on the page, addressing a word only by its key', function () {
        $me = N::member();
        Livewire::actingAs($me)->test(HiddenWords::class)
            ->set('word', 'Spoiler')->call('add')->assertHasNoErrors()->assertSee('spoiler')->assertSet('word', '')
            ->set('word', 'spoiler')->call('add')->assertHasErrors('word')
            ->call('remove', md5('spoiler'))->assertSee('Du verbirgst noch keine Wörter.')
            ->call('remove', md5('gibt es nicht'))->assertNotFound();
        expect($me->fresh()->privacy()->hiddenWords())->toBe([]);
    });

    it('hides Tropfen with the words from the own lists, but never the own ones', function () {
        $me = N::member();
        $friend = N::member();
        followEachOther($me, $friend);
        N::post($friend, ['body' => 'Großer SPOILERalarm zur Serie']);
        N::post($friend, ['body' => 'Ein ruhiger Tropfen']);
        N::post($me, ['body' => 'Mein eigener Spoiler bleibt']);
        app(UpdateHiddenWords::class)->add($me, 'spoiler');

        Livewire::actingAs($me)->test(Source::class)
            ->assertDontSee('Großer SPOILERalarm')->assertSee('Ein ruhiger Tropfen')->assertSee('Mein eigener Spoiler bleibt');
        Livewire::actingAs($friend)->test(Source::class)->assertSee('Großer SPOILERalarm');
    });

    it('hides replies with the words under the own Tropfen from everyone but their writer', function () {
        $owner = N::member();
        $writer = N::member();
        $reader = N::member();
        $origin = N::post($owner, ['body' => 'Meine Frage']);
        app(UpdateHiddenWords::class)->add($owner, 'blöd');
        $hidden = replyTo($origin, $writer, 'Das ist blöd');
        replyTo($origin, $writer, 'Gute Frage');

        foreach ([$owner, $reader] as $viewer) {
            Livewire::actingAs($viewer)->test(PostShow::class, ['post' => $origin])->assertSee('Gute Frage')->assertDontSee('Das ist blöd');
            $this->actingAs($viewer)->get(route('network.posts.show', $hidden))->assertNotFound();
        }
        Livewire::actingAs($writer)->test(PostShow::class, ['post' => $origin])->assertSee('Das ist blöd');
        expect($owner->notifications()->where('data->kind', NoticeKind::Reply->value)->count())->toBe(1);
    });

    it('sends no hint for a mention containing a word', function () {
        $me = N::member(['username' => 'leise_leserin']);
        app(UpdateHiddenWords::class)->add($me, 'werbung');
        app(PublishPost::class)->handle(N::member(), ['body' => '@leise_leserin schau dir diese Werbung an', 'visibility' => 'public']);
        app(PublishPost::class)->handle(N::member(), ['body' => '@leise_leserin schöner Tag', 'visibility' => 'public']);

        expect($me->notifications()->count())->toBe(1);
    });
});

describe('restricting', function () {
    it('shows replies of a restricted person under the own Tropfen only to that person and sends no hints', function () {
        $owner = N::member();
        $restricted = N::member();
        $reader = N::member();
        $origin = N::post($owner, ['body' => 'Meine Frage']);
        app(ManageRestriction::class)->restrict($owner, $restricted);
        $reply = replyTo($origin, $restricted, 'Antwort der eingeschränkten Person');
        followEachOther($restricted, $owner);

        expect($owner->notifications()->count())->toBe(0);
        foreach ([$owner, $reader] as $viewer) {
            Livewire::actingAs($viewer)->test(PostShow::class, ['post' => $origin])->assertDontSee('Antwort der eingeschränkten Person');
            $this->actingAs($viewer)->get(route('network.posts.show', $reply))->assertNotFound();
        }
        Livewire::actingAs($restricted)->test(PostShow::class, ['post' => $origin])->assertSee('Antwort der eingeschränkten Person');
        // Elsewhere the restricted person stays fully visible: only replies under the owner's Tropfen are affected.
        $own = N::post($restricted, ['body' => 'Eigener Tropfen der Person']);
        Livewire::actingAs($owner)->test(PostShow::class, ['post' => $own])->assertSee('Eigener Tropfen der Person');
    });

    it('restricts and lifts from the Tropfen menu, the profile and the safety page, never oneself', function () {
        $me = N::member();
        $other = N::member();
        $post = N::post($other, ['body' => 'Ein Tropfen']);

        Livewire::actingAs($me)->test(PostShow::class, ['post' => $post])->assertSee('Einschränken')->call('restrictUser', $other->id)
            ->assertSee('Nicht mehr einschränken');
        expect($me->fresh()->hasRestricted($other))->toBeTrue();
        Livewire::actingAs($me)->test(SafetyOverview::class)->assertSee($other->display_name)->call('unrestrict', $other->id)
            ->assertSee('Du hast aktuell niemanden eingeschränkt.');
        Livewire::actingAs($me)->test(ProfileShow::class, ['handle' => $other->username])->call('restrictUser', $other->id);
        expect($me->fresh()->hasRestricted($other))->toBeTrue()
            ->and(fn () => app(ManageRestriction::class)->restrict($me, $me))->toThrow(ValidationException::class);
        Livewire::actingAs($me)->test(SafetyOverview::class)->call('unrestrict', N::member()->id)->assertNotFound();
    });
});

describe('devices and login alerts', function () {
    it('names browser and system without fingerprinting', function (string $agent, string $label) {
        expect(DeviceLabel::from($agent))->toBe($label);
    })->with([
        ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0 Safari/537.36', 'Chrome auf Windows'],
        ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0 Safari/537.36 Edg/155.0', 'Edge auf Windows'],
        ['Mozilla/5.0 (iPhone; CPU iPhone OS 19_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/19.0 Mobile/15E148 Safari/604.1', 'Safari auf iOS'],
        ['Mozilla/5.0 (Linux; Android 16) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0 Mobile Safari/537.36', 'Chrome auf Android'],
        ['Mozilla/5.0 (Macintosh; Intel Mac OS X 15.0; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox auf macOS'],
        ['', 'Unbekannter Browser auf unbekanntem System'],
    ]);

    it('records the first device silently and mails on a new one, unless switched off', function () {
        Mail::fake();
        $me = N::member();
        $login = function (string $agent) use ($me): void {
            $this->app->instance('request', Request::create('/anmelden', 'POST', server: ['HTTP_USER_AGENT' => $agent]));
            event(new Login('web', $me->fresh(), false));
        };

        $login('Mozilla/5.0 (Windows NT 10.0) Chrome/155.0 Safari/537.36');
        $login('Mozilla/5.0 (Windows NT 10.0) Chrome/155.0 Safari/537.36');
        Mail::assertNothingSent();

        $login('Mozilla/5.0 (Macintosh; Intel Mac OS X 15.0) Firefox/140.0');
        Mail::assertSent(LoginAlert::class, fn (LoginAlert $mail) => $mail->hasTo($me->email) && $mail->device === 'Firefox auf macOS');

        $me->privacySetting()->updateOrCreate([], ['login_alerts' => false]);
        $login('Mozilla/5.0 (Linux; Android 16) Chrome/155.0 Mobile Safari/537.36');
        Mail::assertSentCount(1);
        expect($me->knownDevices()->pluck('label')->sort()->values()->all())
            ->toBe(['Chrome auf Android', 'Chrome auf Windows', 'Firefox auf macOS']);
    });

    it('signs out one other device or all others, never the current one, and renews remember me', function () {
        config(['session.driver' => 'database']);
        $me = N::member();
        $other = N::member();
        $this->actingAs($me);
        $current = session()->getId();
        $row = fn (string $id, User $user, string $agent) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id,
            'ip_address' => '203.0.113.7', 'user_agent' => $agent, 'payload' => '', 'last_activity' => now()->timestamp]);
        $row($current, $me, 'Mozilla/5.0 (Windows NT 10.0) Chrome/155.0 Safari/537.36');
        $row('phone-session', $me, 'Mozilla/5.0 (Linux; Android 16) Chrome/155.0 Mobile Safari/537.36');
        $row('tablet-session', $me, 'Mozilla/5.0 (iPad; CPU OS 19_0 like Mac OS X) Version/19.0 Safari/604.1');
        $row('foreign-session', $other, 'Mozilla/5.0 (Windows NT 10.0) Firefox/140.0');
        $me->forceFill(['remember_token' => 'old-token'])->save();

        $page = Livewire::test(Devices::class)->assertSee('Chrome auf Android')->assertSee('Netz 203.0.113.…')
            ->assertDontSee('203.0.113.7')->assertDontSee('phone-session')->assertDontSee('Firefox');
        $page->call('endSession', hash('sha256', 'phone-session'))->assertSet('status', 'Abgemeldet: Chrome auf Android.');
        expect(DB::table('sessions')->pluck('id')->all())->toEqualCanonicalizing([$current, 'foreign-session', 'tablet-session'])
            ->and($me->fresh()->remember_token)->not->toBe('old-token');

        Livewire::test(Devices::class)->call('endSession', hash('sha256', $current))->assertNotFound();
        Livewire::test(Devices::class)->call('endSession', hash('sha256', 'foreign-session'))->assertNotFound();
        Livewire::test(Devices::class)->call('endOtherSessions')->assertSet('status', 'Ein anderes Gerät wurde abgemeldet.');
        expect(DB::table('sessions')->pluck('id')->all())->toEqualCanonicalizing([$current, 'foreign-session']);
    });

    it('forgets only own known devices and prunes those unused for a year', function () {
        $me = N::member();
        $mine = $me->knownDevices()->create(['label' => 'Firefox auf Linux', 'last_seen_at' => now()]);
        $old = $me->knownDevices()->create(['label' => 'Safari auf iOS', 'last_seen_at' => now()->subYear()->subDay()]);
        $foreign = N::member()->knownDevices()->create(['label' => 'Chrome auf Windows', 'last_seen_at' => now()]);

        Livewire::actingAs($me)->test(Devices::class)->call('forgetDevice', $foreign->id)->assertNotFound();
        Livewire::actingAs($me)->test(Devices::class)->call('forgetDevice', $mine->id)->assertSet('status', 'Vergessen: Firefox auf Linux.');
        $this->artisan('model:prune', ['--model' => [KnownDevice::class]])->assertSuccessful();

        $this->assertModelMissing($mine);
        $this->assertModelMissing($old);
        $this->assertModelExists($foreign);
    });
});

it('exports hidden words, restricted accounts and known devices', function () {
    $me = N::member();
    $other = N::member();
    app(UpdateHiddenWords::class)->add($me, 'spoiler');
    app(ManageRestriction::class)->restrict($me, $other);
    $me->knownDevices()->create(['label' => 'Firefox auf Linux', 'last_seen_at' => now()]);

    $zip = new ZipArchive;
    $zip->open($this->actingAs($me)->get(route('account.export'))->assertOk()->getFile()->getPathname());
    $data = json_decode($zip->getFromName('oasis-daten.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($data['privatsphaere']['verborgene_woerter'])->toBe(['spoiler'])
        ->and($data['eingeschraenkt'])->toBe([$other->username])
        ->and($data['bekannte_geraete'][0]['geraet'])->toBe('Firefox auf Linux');
});

it('links the further controls from the privacy center', function () {
    $this->actingAs(N::member())->get(route('app.privacy'))->assertOk()
        ->assertSee('Noch mehr Kontrolle')->assertSee(route('app.privacy.words'), false)
        ->assertSee(route('app.privacy.devices'), false)->assertSee('Wer sieht deinen Ort?')
        ->assertSee('E-Mail bei Anmeldung von einem neuen Gerät');
    $this->actingAs(N::member())->get(route('app.privacy.words'))->assertOk()->assertSee('Verborgene Wörter');
    $this->actingAs(N::member())->get(route('app.privacy.devices'))->assertOk()->assertSee('Wo du angemeldet bist');
});
