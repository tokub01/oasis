<?php

use App\Models\GiftMembership;
use App\Models\User;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\Support\ChatFixtures as Chat;
use Tests\Support\NetworkFixtures as Network;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Notification::fake();
    Event::fake([WebhookReceived::class, WebhookHandled::class]);
    config()->set('cashier.webhook.secret', 'whsec_stage9_local_test_only');
});

function stage9GiftEvent(User $buyer, string $status = 'paid'): array
{
    return ['id' => 'evt_stage9_same_event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
        'id' => 'cs_stage9_same_session', 'mode' => 'payment', 'payment_status' => $status,
        'metadata' => ['type' => 'gift_membership', 'purchaser_id' => (string) $buyer->id,
            'purchaser_email' => $buyer->email, 'tier' => 'oase', 'months' => '12', 'amount' => '5000'],
    ]]];
}

function stage9Webhook(array $event, bool $valid = true): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body,
        $valid ? config('cashier.webhook.secret') : 'incorrect_local_test_secret');

    return test()->call('POST', route('cashier.webhook'), [], [], [],
        ['HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature, 'CONTENT_TYPE' => 'application/json'], $body);
}

it('rejects a forged Stripe signature without creating a gift', function () {
    stage9Webhook(stage9GiftEvent(User::factory()->create()), false)->assertForbidden();
    expect(GiftMembership::count())->toBe(0);
});

it('does not issue a redeemable gift before delayed payment succeeds', function () {
    stage9Webhook(stage9GiftEvent(User::factory()->create(), 'unpaid'))->assertOk();
    expect(GiftMembership::count())->toBe(0);
});

it('issues only one gift when Stripe retries the same paid event', function () {
    $event = stage9GiftEvent(User::factory()->create());
    stage9Webhook($event)->assertOk();
    stage9Webhook($event)->assertOk();
    expect(GiftMembership::count())->toBe(1);
});

it('makes existing oasis posts private when a host switches the oasis to members', function () {
    $host = Network::member();
    $oasis = Network::oasis($host);
    $post = Network::post($host, ['oasis_id' => $oasis->id, 'body' => 'stage9 formerly public content']);
    $this->get(route('network.posts.show', $post))->assertOk();
    $this->actingAs($host)->put(route('network.oases.update', $oasis), [
        'name' => $oasis->name, 'description' => $oasis->description, 'house_rules' => $oasis->house_rules,
        'visibility' => 'members', 'color' => 'sun',
    ])->assertRedirect();
    expect($oasis->fresh()->visibility->value)->toBe('members');
    Auth::logout();
    $this->get(route('network.oases.show', $oasis))->assertNotFound();
    $this->get(route('network.posts.show', $post))->assertNotFound();
});

it('hides blocked accounts from exact username chat discovery', function () {
    [$alpha] = Chat::user();
    [$beta] = Chat::user();
    $this->actingAs($alpha)->post(route('safety.block.store', $beta))->assertRedirect();
    $this->actingAs($alpha->fresh())->getJson(route('chat.api.users.index', ['username' => $beta->username]))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('bounds reset emails per IP across different existing accounts', function () {
    $users = User::factory()->count(10)->create();
    $statuses = [];
    foreach ($users as $user) {
        $statuses[] = $this->post(route('password.email'), ['email' => $user->email])->status();
    }
    Notification::assertSentTimes(ResetPassword::class, 6);
    expect($statuses)->toContain(429);
});

it('does not copy sensitive provider exception text into checkout logs', function () {
    config()->set('oasis.checkout_enabled', true);
    $marker = 'stage9-sensitive-customer@example.tld';
    $service = Mockery::mock(StripeMembershipService::class);
    $service->shouldReceive('createSubscriptionCheckout')->once()->andThrow(new RuntimeException($marker));
    $this->app->instance(StripeMembershipService::class, $service);
    Log::spy();
    $this->actingAs(User::factory()->create())->post('/mitglied-werden/checkout', [
        'stufe' => 'oase', 'rhythmus' => 'monat', 'zahlart' => 'karte',
    ])->assertRedirect();
    Log::shouldHaveReceived('error')->once()->with('Stripe checkout initiation failed.', [
        'exception_class' => RuntimeException::class, 'provider_code' => null,
    ]);
});

it('denies an outsider access to conversation metadata and key grants', function () {
    [$owner, $keys] = Chat::user();
    [$member] = Chat::user();
    [$outsider] = Chat::user();
    $conversation = Chat::conversation($owner, $keys, [$member]);
    foreach (['conversations.show', 'keys.index', 'grants.index', 'messages.index'] as $route) {
        $this->actingAs($outsider)->getJson(route('chat.api.'.$route, $conversation))->assertForbidden();
    }
});

it('denies guest access to existing images after their oasis becomes members only', function () {
    Storage::fake('local');
    config()->set('oasis.media_disk', 'local');
    $host = Network::member();
    $oasis = Network::oasis($host);
    $post = Network::post($host, ['oasis_id' => $oasis->id]);
    Storage::disk('local')->put('stage9-test.webp', 'private image sentinel');
    $image = $post->images()->create(['path' => 'stage9-test.webp', 'alt' => 'Private image',
        'width' => 1, 'height' => 1, 'position' => 0]);
    $this->actingAs($host)->put(route('network.oases.update', $oasis), [
        'name' => $oasis->name, 'description' => $oasis->description, 'house_rules' => $oasis->house_rules,
        'visibility' => 'members', 'color' => 'sun',
    ])->assertRedirect();
    Auth::logout();
    $response = $this->get(route('network.images.show', [$post, $image]));
    $response->assertNotFound();
});
