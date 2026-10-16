<?php

use App\Livewire\Membership\Checkout;
use App\Models\GiftMembership;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\ChatFixtures as Chat;
use Tests\Support\MollieFixtures;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as Network;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Notification::fake();
});

function stage9GiftEvent(User $buyer, string $status = 'paid'): array
{
    $intent = MollieFixtures::intent($buyer, 'gift', 'tr_stage9same');

    return MollieFixtures::resource($intent, $status === 'unpaid' ? 'pending' : $status);
}

function stage9Webhook(array $event, bool $valid = true): TestResponse
{
    Mollie::fake([GetPaymentRequest::class => $valid
        ? MockResponse::ok($event) : MockResponse::notFound()]);

    return test()->postJson(route('mollie.webhook'), ['id' => $event['id'], 'status' => 'paid']);
}

it('rejects a forged Mollie payment identifier without creating a gift', function () {
    stage9Webhook(stage9GiftEvent(User::factory()->create()), false)->assertStatus(503);
    expect(GiftMembership::count())->toBe(0);
});

it('does not issue a redeemable gift before delayed payment succeeds', function () {
    stage9Webhook(stage9GiftEvent(User::factory()->create(), 'unpaid'))->assertOk();
    expect(GiftMembership::count())->toBe(0);
});

it('issues only one gift when Mollie retries the same paid event', function () {
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
    NetworkActions::saveOasis([
        'name' => $oasis->name, 'description' => $oasis->description, 'house_rules' => $oasis->house_rules,
        'visibility' => 'members', 'color' => 'sun',
    ], $oasis, $host)->assertRedirect();
    expect($oasis->fresh()->visibility->value)->toBe('members');
    Auth::logout();
    $this->get(route('network.oases.show', $oasis))->assertNotFound();
    $this->get(route('network.posts.show', $post))->assertNotFound();
});

it('hides blocked accounts from exact username chat discovery', function () {
    [$alpha] = Chat::user();
    [$beta] = Chat::user();
    NetworkActions::post('blockUser', $beta->id, $alpha)->assertHasNoErrors();
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
    $service = Mockery::mock(MollieMembershipService::class);
    $service->shouldReceive('createSubscriptionCheckout')->once()->andThrow(new RuntimeException($marker));
    $this->app->instance(MollieMembershipService::class, $service);
    Log::spy();
    Livewire::actingAs(User::factory()->create())
        ->test(Checkout::class)
        ->set('stufe', 'oase')
        ->set('rhythmus', 'monat')
        ->set('zahlart', 'karte')
        ->call('checkout');
    Log::shouldHaveReceived('error')->once()->with('Mollie checkout initiation failed.', [
        'exception_class' => RuntimeException::class,
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
    NetworkActions::saveOasis([
        'name' => $oasis->name, 'description' => $oasis->description, 'house_rules' => $oasis->house_rules,
        'visibility' => 'members', 'color' => 'sun',
    ], $oasis, $host)->assertRedirect();
    Auth::logout();
    $response = $this->get(route('network.images.show', [$post, $image]));
    $response->assertNotFound();
});
