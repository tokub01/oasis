<?php

use App\Actions\Network\ManageFollows;
use App\Livewire\Account\Settings;
use App\Livewire\Membership\Overview;
use App\Livewire\Oasis\ContentNotice;
use App\Models\GiftMembership;
use App\Models\Message;
use App\Models\Notifications\NotificationPreference;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\ChatFixtures as F;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/*
 * Stage 9 – Claude's functional audit: complete member journeys across packages (accounts, network, hints, search,
 * safety, membership, chat, account deletion). Tests marked "FINDING" document a confirmed gap and are expected to
 * fail until it is fixed (see C:\Users\tobia\oasis-handoff\stage9-claude-function.md).
 */
beforeEach(function () {
    $this->withoutVite();
    Storage::fake(config('oasis.media_disk'));
});

it('journey: sign up, verify, set up a profile, post with mention and topic, get found and answered', function () {
    // Real notifications: hints are database notifications (the verification mail goes to the array mailer).
    RateLimiter::clear('registration|127.0.0.1');
    $mira = N::member(['username' => 'mira', 'display_name' => 'Mira Sol']);

    $this->post(route('register'), [
        'display_name' => 'Neu Hier', 'username' => 'neu_hier', 'email' => 'neu@example.tld', 'birth_date' => '1995-03-03',
        'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'terms' => '1',
    ])->assertRedirect('/app');
    $me = User::where('username', 'neu_hier')->sole();
    $this->get('/app')->assertRedirect(route('verification.notice'));

    $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $me->id, 'hash' => sha1($me->email)]));
    expect($me->fresh()->hasVerifiedEmail())->toBeTrue();

    NetworkActions::saveProfile(['bio' => 'Hallo!', 'avatar_color' => 'spring'])->assertHasNoErrors();
    NetworkActions::profile('follow', $mira)->assertHasNoErrors();
    NetworkActions::publish(['body' => 'Erster Tropfen für @mira #Anfang', 'visibility' => 'public'])->assertHasNoErrors();
    $post = Post::where('user_id', $me->id)->sole();

    expect($mira->fresh()->notifications->pluck('data.kind')->sort()->values()->all())->toBe(['follow', 'mention']);
    $this->get(route('search.tags.show', 'anfang'))->assertSee('Erster Tropfen');

    NetworkActions::publish(['body' => 'Willkommen!', 'visibility' => 'public', 'reply_to' => $post->id], $mira);
    expect($me->fresh()->notifications->pluck('data.kind')->all())->toContain('reply');
    $this->actingAs($me->fresh())->get(route('notifications.index'))->assertOk()->assertSee('Mira Sol');
});

it('journey: trial ends → read-only everywhere, a redeemed gift restores writing', function () {
    $me = N::member(['trial_ends_at' => now()->addDay()]);
    $post = N::post(N::member());
    $this->travel(2)->days();

    $this->actingAs($me)->get(route('app.home'))->assertOk()->assertSee('nur zum Lesen');
    NetworkActions::publish(['body' => 'x', 'visibility' => 'public'])->assertForbidden();
    NetworkActions::post('react', $post->id)->assertForbidden();
    $this->get(route('account.export'))->assertOk();
    $this->get(route('app.chat'))->assertSee('data-can-participate="0"', false);

    $gift = GiftMembership::factory()->create(['redeemed_by_user_id' => null, 'redeemed_at' => null, 'expires_at' => null]);
    Livewire::test(Overview::class)->set('code', $gift->code)->call('redeem')->assertHasNoErrors();
    expect($me->fresh()->canParticipate())->toBeTrue();
    NetworkActions::publish(['body' => 'Wieder dabei', 'visibility' => 'public'])->assertHasNoErrors();
});

it('journey: deleting an account leaves the network consistent', function () {
    $leaving = N::member(['username' => 'geht']);
    $stays = N::member();
    $original = N::post($leaving, ['body' => 'Mein Original']);
    $reply = N::post($stays, ['body' => 'Antwort darauf', 'reply_to_id' => $original->id]);
    $carry = N::post($stays, ['body' => 'Weitergetragen', 'carried_post_id' => $original->id]);
    app(ManageFollows::class)->follow($leaving, $stays);
    $stays->bookmarks()->create(['post_id' => $original->id]);

    $this->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($leaving)->test(Settings::class)->set('confirm', true)->call('deleteAccount')->assertRedirect();
    expect(User::find($leaving->id))->toBeNull();

    $this->actingAs($stays)->get(route('network.posts.show', $reply))->assertOk()->assertSee('nicht mehr verfügbar');
    $this->get(route('network.posts.show', $carry))->assertOk()->assertSee('Dieser Tropfen ist nicht mehr verfügbar.');
    $this->get(route('notifications.index'))->assertOk();
    $this->get(route('safety.bookmarks'))->assertOk();
    $this->get(route('app.home'))->assertOk();
});

it('FINDING F1: deleting an account removes its uploaded images from storage (GDPR erasure)', function () {
    $leaving = N::member();
    NetworkActions::publish([
        'body' => 'Mit Bild', 'visibility' => 'public', 'images' => [UploadedFile::fake()->image('a.png')], 'alts' => ['A'],
    ], $leaving);
    expect(Storage::disk(config('oasis.media_disk'))->allFiles())->toHaveCount(1);

    $this->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($leaving)->test(Settings::class)->set('confirm', true)->call('deleteAccount');

    expect(Storage::disk(config('oasis.media_disk'))->allFiles())->toBe([]);
});

it('FINDING F7: the export contains the own Tropfen with images, follows and bookmarks', function () {
    $me = N::member(['username' => 'exporteurin']);
    $mira = N::member(['username' => 'mira']);
    NetworkActions::publish([
        'body' => 'Mein exportierter Tropfen', 'visibility' => 'public',
        'images' => [UploadedFile::fake()->image('a.png')], 'alts' => ['Ein Bild'],
    ], $me);
    app(ManageFollows::class)->follow($me, $mira);
    $me->bookmarks()->create(['post_id' => N::post($mira)->id]);
    N::post($mira, ['body' => 'Fremder Tropfen, nicht im Export']);

    $response = $this->get(route('account.export'))->assertOk()->assertDownload('oasis-daten.zip');
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $data = json_decode($zip->getFromName('oasis-daten.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($data['tropfen'])->pluck('text')->all())->toBe(['Mein exportierter Tropfen'])
        ->and($data['tropfen'][0]['bilder'][0]['beschreibung'])->toBe('Ein Bild')
        ->and($zip->getFromName($data['tropfen'][0]['bilder'][0]['datei']))->not->toBeFalse()
        ->and($data['ich_folge']['menschen'])->toBe(['mira'])
        ->and($data['gemerkt'])->toHaveCount(1);
});

it('FINDING F2: a blockade also stops sending in an existing direct chat', function () {
    [$alice, $aliceIdentity] = F::user();
    [$bob] = F::user();
    $conversation = F::conversation($alice, $aliceIdentity, [$bob]);
    $bob->blocks()->create(['blocked_user_id' => $alice->id]);

    $this->actingAs($alice)->postJson('/app/chat/api/conversations/'.$conversation->id.'/messages', [
        'envelope' => F::envelope($conversation, $alice, $aliceIdentity),
    ])->assertStatus(403);
    expect(Message::count())->toBe(0);
});

it('FINDING F3: deleting an account with a running paid membership is refused or cancels it', function () {
    $member = N::member();
    $member->subscriptions()->create([
        'type' => 'oase', 'mollie_id' => 'sub_test', 'status' => 'active', 'paid_until' => now()->addMonth(),
    ]);

    $this->withSession(['auth.password_confirmed_at' => time()]);
    $response = Livewire::actingAs($member)->test(Settings::class)->set('confirm', true)->call('deleteAccount');

    // Acceptable: refuse with a clear message (account stays) – never delete silently while Mollie keeps charging.
    expect(User::find($member->id))->not->toBeNull();
    $response->assertHasErrors();
});

it('FINDING F4: the last host can close an oasis instead of being stuck with it', function () {
    $host = N::member();
    $oasis = N::oasis($host);

    NetworkActions::close($oasis, ['confirm' => true], $host)->assertRedirect(route('network.oases.index'));
    expect($oasis->fresh())->toBeNull();
});

it('FINDING F5: profiles and oases can be reported from the app, not only Tropfen', function () {
    $other = N::member();
    $other->privacySetting()->create(['profile_audience' => 'everyone']);
    $oasis = N::oasis($other);
    $this->actingAs(N::member());

    $this->get(route('oasis.profil', $other->username))
        ->assertSee(route('app.report', ['url' => route('oasis.profil', $other->username)]), false);
    $this->get(route('network.oases.show', $oasis))
        ->assertSee(route('app.report', ['url' => route('network.oases.show', $oasis)]), false);
});

it('journey: the reporter of a Tropfen does not have to type name and e-mail again', function () {
    $me = N::member(['display_name' => 'Melde Person']);
    $this->actingAs($me)->get(route('oasis.melden', ['url' => 'https://oasis.tld/tropfen/1']))->assertSeeLivewire(ContentNotice::class);
    Livewire::withQueryParams(['url' => 'https://oasis.tld/tropfen/1'])->test(ContentNotice::class)
        ->assertSet('form.url', 'https://oasis.tld/tropfen/1')
        ->assertSet('form.name', 'Melde Person')->assertSet('form.email', $me->email);
});

it('journey: hint preferences survive and a muted person never shows up in hints', function () {
    $me = N::member(['username' => 'ich']);
    $loud = N::member();
    NotificationPreference::create(['user_id' => $me->id, 'kinds' => ['mention' => true, 'follow' => true, 'reply' => true, 'carry' => true, 'invitation' => true, 'host' => true], 'digest_enabled' => false]);
    NetworkActions::post('muteUser', $loud->id, $me)->assertHasNoErrors();

    NetworkActions::publish(['body' => 'Hey @ich', 'visibility' => 'public'], $loud);
    expect($me->fresh()->notifications)->toBeEmpty();
});

it('FINDING F8: deleting the only host hands the oasis over or closes an empty one', function () {
    $host = N::member();
    $shared = N::oasis($host, ['name' => 'Mit Mitgliedern']);
    $alone = N::oasis($host, ['name' => 'Ganz allein']);
    $member = N::member();
    app(ManageFollows::class)->follow($member, $shared);

    $this->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($host)->test(Settings::class)->set('confirm', true)->call('deleteAccount')->assertRedirect();

    expect($shared->fresh()->isHost($member))->toBeTrue()
        ->and(Oasis::whereKey($alone->id)->exists())->toBeFalse();
});

it('lets a host close an oasis after confirming; its Tropfen stay with their authors', function () {
    $host = N::member();
    $oasis = N::oasis($host);
    $post = N::post($host, ['oasis_id' => $oasis->id, 'body' => 'Bleibt erhalten']);
    $this->actingAs(N::member())->get(route('network.oases.close', $oasis))->assertForbidden();

    $this->actingAs($host)->get(route('network.oases.close', $oasis))->assertOk()->assertSee('schließen?');
    NetworkActions::close($oasis, [])->assertHasErrors('confirm');
    NetworkActions::close($oasis, ['confirm' => '1'])->assertRedirect(route('network.oases.index'));

    expect(Oasis::whereKey($oasis->id)->exists())->toBeFalse()
        ->and($post->fresh()->oasis_id)->toBeNull();
});
