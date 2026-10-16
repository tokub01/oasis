<?php

use App\Actions\Network\StorePostImage;
use App\Events\Chat\MessageSent;
use App\Livewire\Membership\Checkout;
use App\Models\Block;
use App\Models\GiftMembership;
use App\Models\MembershipPayment;
use App\Models\Message;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use App\Support\Network\PostInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ChatFixtures as Chat;
use Tests\Support\MollieFixtures;
use Tests\Support\NetworkFixtures as Network;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Notification::fake();
    Event::fake([MessageSent::class]);
});

function backendFixGiftEvent(User $buyer, string $type, ?string $status): array
{
    $intent = MembershipPayment::where('mollie_payment_id', 'tr_backendfix')->first()
        ?? MollieFixtures::intent($buyer, 'gift', 'tr_backendfix');

    return MollieFixtures::resource($intent, $status === 'paid' ? 'paid' : 'pending');
}

function backendFixWebhook(array $event): TestResponse
{
    MollieFixtures::fake($event);

    return test()->postJson(route('mollie.webhook'), ['id' => $event['id']]);
}

it('fulfills delayed gifts only after payment and tolerates all subsequent retries', function () {
    $buyer = User::factory()->create();
    backendFixWebhook(backendFixGiftEvent($buyer, 'checkout.session.completed', 'unpaid'))->assertOk();
    expect(GiftMembership::count())->toBe(0);
    backendFixWebhook(backendFixGiftEvent($buyer, 'checkout.session.async_payment_succeeded', 'paid'))->assertOk();
    $gift = GiftMembership::sole();
    backendFixWebhook(backendFixGiftEvent($buyer, 'checkout.session.completed', 'paid'))->assertOk();
    backendFixWebhook(backendFixGiftEvent($buyer, 'checkout.session.async_payment_succeeded', 'paid'))->assertOk();
    expect(GiftMembership::count())->toBe(1)->and(GiftMembership::sole()->code)->toBe($gift->code);
});

it('does not fulfill failed delayed payments or missing and unpaid statuses', function () {
    $buyer = User::factory()->create();
    foreach ([['checkout.session.async_payment_failed', 'unpaid'],
        ['checkout.session.completed', null], ['checkout.session.async_payment_succeeded', 'unpaid']] as [$type, $status]) {
        backendFixWebhook(backendFixGiftEvent($buyer, $type, $status))->assertOk();
    }
    expect(GiftMembership::count())->toBe(0);
});

it('recovers the losing insert when another fulfillment wins after the initial lookup', function () {
    $buyer = User::factory()->create();
    $metadata = ['tier' => 'oase', 'months' => 12, 'amount' => 5000, 'purchaser_id' => $buyer->id];
    $injected = false;
    DB::listen(function ($query) use (&$injected, $buyer) {
        if (! $injected && str_starts_with(strtolower($query->sql), 'select')
            && str_contains($query->sql, 'gift_memberships') && in_array('cs_backend_race', $query->bindings, true)) {
            $injected = true;
            GiftMembership::create(['code' => 'OASIS-RACE-WINS', 'tier' => 'oase', 'months' => 12,
                'amount' => 5000, 'currency' => 'eur', 'purchaser_user_id' => $buyer->id,
                'mollie_payment_id' => 'cs_backend_race']);
        }
    });
    $gift = app(MollieMembershipService::class)->fulfillGiftPurchase($metadata, 'cs_backend_race');
    expect($injected)->toBeTrue()->and($gift->code)->toBe('OASIS-RACE-WINS')
        ->and(GiftMembership::count())->toBe(1);
});

it('rejects sends to a blocked active member in either direction in existing chats', function (string $type, bool $reverse) {
    [$sender, $keys] = Chat::user();
    [$target] = Chat::user();
    $conversation = Chat::conversation($sender, $keys, [$target]);
    $conversation->update(['type' => $type]);
    $sender->blockedUserIds();
    Block::create(['user_id' => $reverse ? $target->id : $sender->id,
        'blocked_user_id' => $reverse ? $sender->id : $target->id]);
    $this->actingAs($sender)->postJson(route('chat.api.messages.store', $conversation),
        ['envelope' => Chat::envelope($conversation, $sender, $keys)])->assertForbidden();
    expect(Message::count())->toBe(0);
    Event::assertNotDispatched(MessageSent::class);
})->with([['direct', false], ['direct', true], ['group', false], ['group', true]]);

it('permits a neutral group sender and ignores blocks with departed members', function () {
    [$owner, $ownerKeys] = Chat::user();
    [$blocked] = Chat::user();
    [$neutral, $neutralKeys] = Chat::user();
    $conversation = Chat::conversation($owner, $ownerKeys, [$blocked, $neutral]);
    Block::create(['user_id' => $owner->id, 'blocked_user_id' => $blocked->id]);
    $this->actingAs($neutral)->postJson(route('chat.api.messages.store', $conversation),
        ['envelope' => Chat::envelope($conversation, $neutral, $neutralKeys)])->assertCreated();
    $conversation->memberships()->where('user_id', $blocked->id)->update(['left_at' => now()]);
    $this->actingAs($owner->fresh())->postJson(route('chat.api.messages.store', $conversation),
        ['envelope' => Chat::envelope($conversation, $owner, $ownerKeys)])->assertCreated();
});

it('filters blocked history before pagination while retaining historical verification keys', function (bool $reverse) {
    [$owner, $keys] = Chat::user();
    [$viewer] = Chat::user();
    [$neutral, $neutralKeys] = Chat::user();
    $conversation = Chat::conversation($owner, $keys, [$viewer, $neutral]);
    for ($i = 0; $i < 52; $i++) {
        foreach ([[$owner, $keys], [$neutral, $neutralKeys]] as [$sender, $identity]) {
            Message::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'key_version' => 1,
                'envelope' => Chat::envelope($conversation, $sender, $identity), 'expires_at' => now()->addDays(7),
                'created_at' => now()]);
        }
    }
    Block::create(['user_id' => $reverse ? $owner->id : $viewer->id,
        'blocked_user_id' => $reverse ? $viewer->id : $owner->id]);
    $this->actingAs($viewer->fresh());
    $first = $this->getJson(route('chat.api.messages.index', $conversation))->assertOk()->assertJsonCount(50, 'data');
    expect(collect($first->json('data'))->pluck('envelope.sender_id')->unique()->all())->toBe([(string) $neutral->id]);
    $cursor = $first->json('next_before_id');
    expect($cursor)->not->toBeNull();
    $second = $this->getJson(route('chat.api.messages.index', [$conversation, 'before_id' => $cursor]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('next_before_id', null);
    expect(collect($second->json('data'))->pluck('envelope.sender_id')->unique()->all())->toBe([(string) $neutral->id]);
    $this->getJson(route('chat.api.keys.index', $conversation))->assertOk()->assertJsonCount(3, 'data');
    $this->getJson(route('chat.api.users.index', ['username' => $owner->username]))->assertOk()->assertJsonCount(0, 'data');
})->with([false, true]);

/** Header-only PNG fixture: dimensions can be read without allocating a bitmap. */
function backendFixImageHeader(int $width, int $height): UploadedFile
{
    $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
    $bytes = "\x89PNG\r\n\x1a\n".pack('N', strlen($header)).'IHDR'.$header.pack('N', crc32('IHDR'.$header));

    return UploadedFile::fake()->createWithContent('dimensions.png', $bytes);
}

it('bounds either input edge before processing and accepts the exact edge boundary', function () {
    $rules = PostInput::rules()['images.*'];
    foreach ([[12001, 1], [1, 12001]] as [$width, $height]) {
        $validator = Validator::make(['image' => backendFixImageHeader($width, $height)], ['image' => $rules]);
        expect($validator->fails())->toBeTrue();
        expect($validator->failed()['image'])->toHaveKey('Dimensions');
    }
    $validator = Validator::make(['image' => backendFixImageHeader(12000, 1)], ['image' => $rules]);
    expect($validator->passes())->toBeTrue();
});

it('rejects input above forty megapixels before GD decoding or storage', function () {
    Storage::fake('local');
    config()->set('oasis.media_disk', 'local');
    $post = Network::post(Network::member());
    try {
        app(StorePostImage::class)->handle($post, backendFixImageHeader(7000, 6000), 'Oversized input', 0);
        $this->fail('The pixel budget must reject this input before GD sees the header-only fixture.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['images.0'][0])->toContain('40 Megapixel');
    }
    expect(Storage::disk('local')->allFiles())->toBe([])->and($post->images()->count())->toBe(0);
});

it('logs payment failures with only exception class and provider code', function () {
    config()->set('oasis.checkout_enabled', true);
    $error = new RuntimeException('private-customer@example.tld');
    $service = Mockery::mock(MollieMembershipService::class);
    $service->shouldReceive('createSubscriptionCheckout')->once()->andThrow($error);
    $this->app->instance(MollieMembershipService::class, $service);
    Log::spy();
    Livewire::actingAs(User::factory()->create())
        ->test(Checkout::class)
        ->set('stufe', 'oase')
        ->set('rhythmus', 'monat')
        ->set('zahlart', 'karte')
        ->call('checkout');
    Log::shouldHaveReceived('error')->once()->with(
        'Mollie checkout initiation failed.', ['exception_class' => $error::class]);
});

it('limits reset attempts by IP independently of target account and keeps neutral responses', function () {
    $user = User::factory()->create();
    foreach ([$user->email, 'unknown@example.tld', $user->email, 'other@example.tld',
        'another@example.tld', 'last@example.tld'] as $email) {
        $this->postJson(route('password.email'), ['email' => $email])->assertOk()
            ->assertExactJson(['message' => __('passwords.sent')]);
    }
    $this->postJson(route('password.email'), ['email' => 'seventh@example.tld'])->assertStatus(429);
    Notification::assertCount(1);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
        ->postJson(route('password.email'), ['email' => 'independent@example.tld'])->assertOk();
});

it('acknowledges failed payments only after checking the provider and without customer logging', function () {
    $buyer = User::factory()->create();
    $intent = MollieFixtures::intent($buyer, 'gift', 'tr_failed');
    backendFixWebhook(MollieFixtures::resource($intent, 'failed'))->assertOk();
    expect(GiftMembership::count())->toBe(0);
});

it('applies the reset IP budget to compiled routes as well', function () {
    $router = app(Router::class);
    $router->setCompiledRoutes($router->getRoutes()->compile());
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/passwort-vergessen', ['email' => 'unknown'.$i.'@example.tld'])->assertOk();
    }
    $this->postJson('/passwort-vergessen', ['email' => 'seventh@example.tld'])->assertStatus(429);
});
