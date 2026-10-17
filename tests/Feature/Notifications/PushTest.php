<?php

/* Push hints (docs/oasis-pwa.md): subscriptions, encryption per RFC 8291, VAPID per RFC 8292, quiet hours, cleanup. */

use App\Actions\Account\ExportAccount;
use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Jobs\Notifications\SendPushHint;
use App\Livewire\Notifications\Settings;
use App\Models\Notifications\NotificationPreference;
use App\Models\Notifications\PushSubscription;
use App\Support\Notifications\WebPush;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

const PUSH_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

beforeEach(function () {
    $this->withoutVite();
    $keys = WebPush::generateKeys();
    config(['oasis.push.public_key' => $keys['public'], 'oasis.push.private_key' => $keys['private'], 'oasis.push.subject' => 'mailto:info@oasis.tld']);
});

/** A browser key pair and auth secret, as a real browser would create them. */
function pushBrowser(): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $ec = openssl_pkey_get_details($key)['ec'];
    $public = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

    return ['key' => $key, 'public' => $public, 'auth' => random_bytes(16)];
}

function pushPublicKey(string $point): OpenSSLAsymmetricKey
{
    return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point), 64, "\n")."-----END PUBLIC KEY-----\n");
}

/** What the browser does with an aes128gcm body (RFC 8291 section 3 and 4, receiver side). */
function pushDecrypt(string $body, array $browser): string
{
    $salt = substr($body, 0, 16);
    expect(unpack('N', substr($body, 16, 4))[1])->toBe(4096)->and(ord($body[20]))->toBe(65);
    $serverPublic = substr($body, 21, 65);
    $sealed = substr($body, 86);
    $shared = openssl_pkey_derive(pushPublicKey($serverPublic), $browser['key']);
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$browser['public'].$serverPublic, $browser['auth']);
    $plain = openssl_decrypt(substr($sealed, 0, -16), 'aes-128-gcm', hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt),
        OPENSSL_RAW_DATA, hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt), substr($sealed, -16));
    expect($plain)->not->toBeFalse()->and(substr($plain, -1))->toBe("\x02");

    return substr($plain, 0, -1);
}

function pushSubscribe($user, array $browser, string $endpoint = PUSH_ENDPOINT): PushSubscription
{
    return PushSubscription::create(['user_id' => $user->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
        'p256dh' => WebPush::base64UrlEncode($browser['public']), 'auth' => WebPush::base64UrlEncode($browser['auth']), 'device' => 'Chrome auf Android']);
}

it('encrypts a message that only the browser can read and signs it with VAPID', function () {
    Http::fake(['*' => Http::response('', 201)]);
    $browser = pushBrowser();
    $subscription = pushSubscribe(N::member(), $browser);

    expect(WebPush::fromConfig()->send($subscription, ['title' => 'Oasis', 'body' => 'Du hast einen neuen Hinweis.']))->toBeTrue();

    Http::assertSent(function (Request $request) use ($browser) {
        expect(json_decode(pushDecrypt($request->body(), $browser), true))->toBe(['title' => 'Oasis', 'body' => 'Du hast einen neuen Hinweis.'])
            ->and($request->header('Content-Encoding'))->toBe(['aes128gcm'])
            ->and($request->header('TTL'))->toBe(['86400']);

        preg_match('/^vapid t=([^,]+), k=(.+)$/', $request->header('Authorization')[0], $match);
        expect($match[2])->toBe(config('oasis.push.public_key'));
        [$header, $claims, $signature] = explode('.', $match[1]);
        $payload = json_decode(WebPush::base64UrlDecode($claims), true);
        expect($payload['aud'])->toBe('https://fcm.googleapis.com')->and($payload['sub'])->toBe('mailto:info@oasis.tld')
            ->and($payload['exp'])->toBeGreaterThan(time());

        // JWT ES256 signature r | s back to DER for OpenSSL.
        $raw = WebPush::base64UrlDecode($signature);
        $int = fn (string $bytes) => (ord(ltrim($bytes, "\0")[0]) & 0x80 ? "\0" : '').ltrim($bytes, "\0");
        $r = $int(substr($raw, 0, 32));
        $s = $int(substr($raw, 32));
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        expect(openssl_verify($header.'.'.$claims, $der, pushPublicKey(WebPush::base64UrlDecode($match[2])), OPENSSL_ALGO_SHA256))->toBe(1);

        return true;
    });
    expect($subscription->fresh()->last_used_at)->not->toBeNull();
});

it('deletes a subscription the push service no longer knows', function () {
    Http::fake(['*' => Http::response('', 410)]);
    $subscription = pushSubscribe(N::member(), pushBrowser());

    expect(WebPush::fromConfig()->send($subscription, ['title' => 'Oasis']))->toBeFalse()
        ->and(PushSubscription::count())->toBe(0);
});

it('only accepts endpoints of known push services over https', function (string $endpoint, bool $allowed) {
    expect(WebPush::allowedEndpoint($endpoint))->toBe($allowed);
})->with([
    ['https://fcm.googleapis.com/fcm/send/x', true],
    ['https://updates.push.services.mozilla.com/wpush/v2/x', true],
    ['https://web.push.apple.com/x', true],
    ['https://wns2-par02p.notify.windows.com/w/?token=x', true],
    ['http://fcm.googleapis.com/fcm/send/x', false],
    ['https://fcm.googleapis.com:8443/x', false],
    ['https://evil.tld/fcm.googleapis.com', false],
    ['https://notify.windows.com.evil.tld/x', false],
    ['https://127.0.0.1/x', false],
]);

it('stores and removes the subscription of the own browser', function () {
    $member = N::member();
    $browser = pushBrowser();
    $json = ['endpoint' => PUSH_ENDPOINT, 'keys' => ['p256dh' => WebPush::base64UrlEncode($browser['public']), 'auth' => WebPush::base64UrlEncode($browser['auth'])]];

    $this->actingAs($member)->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36')
        ->postJson(route('notifications.push.store'), $json)->assertOk();
    expect($member->pushSubscriptions()->sole()->device)->toBe('Chrome auf Android');

    $this->actingAs(N::member())->postJson(route('notifications.push.destroy'), ['endpoint' => PUSH_ENDPOINT])->assertOk();
    expect(PushSubscription::count())->toBe(1);

    $this->actingAs($member)->postJson(route('notifications.push.destroy'), ['endpoint' => PUSH_ENDPOINT])->assertOk();
    expect(PushSubscription::count())->toBe(0);
});

it('rejects foreign push endpoints, bad keys and requests while push is off', function () {
    $member = N::member();
    $browser = pushBrowser();
    $keys = ['p256dh' => WebPush::base64UrlEncode($browser['public']), 'auth' => WebPush::base64UrlEncode($browser['auth'])];

    $this->actingAs($member)->postJson(route('notifications.push.store'), ['endpoint' => 'https://intranet.oasis.tld/hook', 'keys' => $keys])
        ->assertJsonValidationErrors('endpoint');
    $this->postJson(route('notifications.push.store'), ['endpoint' => PUSH_ENDPOINT, 'keys' => ['p256dh' => 'kurz', 'auth' => $keys['auth']]])
        ->assertJsonValidationErrors('keys.p256dh');

    config(['oasis.push.private_key' => null]);
    $this->postJson(route('notifications.push.store'), ['endpoint' => PUSH_ENDPOINT, 'keys' => $keys])->assertNotFound();
    expect(PushSubscription::count())->toBe(0);

    auth()->logout();
    $this->postJson(route('notifications.push.store'), ['endpoint' => PUSH_ENDPOINT, 'keys' => $keys])->assertUnauthorized();
});

it('pushes a neutral notice for a new hint, once per cooldown', function () {
    Http::fake(['*' => Http::response('', 201)]);
    $recipient = N::member();
    $browser = pushBrowser();
    pushSubscribe($recipient, $browser);
    $actor = N::member();

    app(SendHint::class)->handle($recipient, $actor, NoticeKind::Follow, 'follow:1');
    app(SendHint::class)->handle($recipient, N::member(), NoticeKind::Follow, 'follow:2');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($browser, $actor) {
        $message = pushDecrypt($request->body(), $browser);

        return json_decode($message, true) === ['title' => 'Oasis', 'body' => 'Du hast einen neuen Hinweis.', 'url' => '/app/hinweise', 'tag' => 'oasis-hinweise']
            && ! str_contains($message, $actor->username);
    });
});

it('sends no push during quiet hours or a pause, nor for disabled hint kinds', function () {
    Http::fake(['*' => Http::response('', 201)]);
    $recipient = N::member();
    pushSubscribe($recipient, pushBrowser());

    NotificationPreference::create(['user_id' => $recipient->id, 'paused_until' => now()->addDay(), 'kinds' => ['follow' => false]]);
    app(SendHint::class)->handle($recipient, N::member(), NoticeKind::Follow, 'follow:off');
    app(SendHint::class)->handle($recipient, N::member(), NoticeKind::Mention, 'mention:paused');
    (new SendPushHint($recipient->id))->handle();

    Http::assertNothingSent();
    expect($recipient->unreadNotifications()->count())->toBe(1);

    NotificationPreference::where('user_id', $recipient->id)->update(['paused_until' => null]);
    Cache::flush();
    (new SendPushHint($recipient->id))->handle();
    Http::assertSentCount(1);
});

it('lists and removes push devices in the hint settings', function () {
    $member = N::member();
    $own = pushSubscribe($member, pushBrowser());
    $foreign = pushSubscribe(N::member(), pushBrowser(), 'https://fcm.googleapis.com/fcm/send/fremd');

    Livewire::actingAs($member)->test(Settings::class)
        ->assertSee('Geräte mit Push-Hinweisen')->assertSee('Chrome auf Android')
        ->call('removePush', $foreign->id)
        ->call('removePush', $own->id)->assertSet('status', 'Das Gerät bekommt keine Push-Hinweise mehr.')
        ->assertDontSee('Geräte mit Push-Hinweisen');

    expect(PushSubscription::pluck('id')->all())->toBe([$foreign->id]);
});

it('hides the push switch while no VAPID keys are configured', function () {
    config(['oasis.push.public_key' => null]);

    Livewire::actingAs(N::member())->test(Settings::class)
        ->assertSee('Push-Hinweise sind auf Oasis noch nicht eingerichtet.')->assertDontSee('data-oasis="push"', false);
});

it('exports and deletes push devices with the account', function () {
    $member = N::member();
    pushSubscribe($member, pushBrowser());

    $path = app(ExportAccount::class)->handle($member);
    $zip = new ZipArchive;
    $zip->open($path);
    $data = json_decode($zip->getFromName('oasis-daten.json'), true);
    $zip->close();
    unlink($path);
    expect($data['push_geraete'][0]['geraet'])->toBe('Chrome auf Android');

    $member->delete();
    expect(PushSubscription::count())->toBe(0);
});

it('creates VAPID keys of the right shape', function () {
    $this->artisan('oasis:push-keys')->expectsOutputToContain('OASIS_VAPID_PUBLIC=')->assertSuccessful();

    $keys = WebPush::generateKeys();
    expect(strlen(WebPush::base64UrlDecode($keys['public'])))->toBe(65)
        ->and(strlen(WebPush::base64UrlDecode($keys['private'])))->toBe(32);
});
