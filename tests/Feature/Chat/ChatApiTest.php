<?php

use App\Actions\Chat\SendMessage;
use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\EventType;
use App\Enums\Moderation\Priority;
use App\Events\Chat\KeyRotated;
use App\Events\Chat\MessageSent;
use App\Http\Middleware\ChatResponse;
use App\Models\ChatIdentity;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\ModerationCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\ChatFixtures as F;

/* Backend regressions for authorization, ciphertext boundaries, authenticated rotation and expiry. */
beforeEach(function () {
    Event::fake([MessageSent::class, KeyRotated::class]);
    [$this->owner, $this->ownerIdentity] = F::user();
    [$this->member, $this->memberIdentity] = F::user();
    $this->conversation = F::conversation($this->owner, $this->ownerIdentity, [$this->member]);
    $this->base = '/app/chat/api/conversations/'.$this->conversation->id;
    $this->actingAs($this->owner);
});

it('requires authentication and verified email with uncached JSON errors', function () {
    auth()->logout();
    $this->get('/app/chat/api/identity')->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(User::factory()->unverified()->create())->get('/app/chat/api/identity')->assertForbidden();
});

it('retrieves only the caller identity and excludes private blobs from public responses', function () {
    $this->getJson('/app/chat/api/identity')->assertOk()->assertJsonPath('data.user_id', (string) $this->owner->id)
        ->assertHeader('Cache-Control', 'no-store, private');
    $response = $this->getJson($this->base.'/public-keys')->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->getContent())->not->toContain('locked_identity', 'ciphertext', $this->member->email);
    $this->getJson('/app/chat/api/users?username='.$this->member->username)->assertOk()
        ->assertJsonPath('data.0.id', (string) $this->member->id);
});

it('supports first identity setup without recording a key change', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/app/chat/api/identity')->assertJsonPath('data', null);
    $identity = F::identity();
    $this->putJson('/app/chat/api/identity', array_intersect_key($identity, array_flip(['locked_identity', 'public_keys'])))
        ->assertOk()->assertJsonPath('data.key_changed_at', null);
});

it('relocks the same identity without invalidating conversation keys', function () {
    $input = ['locked_identity' => $this->ownerIdentity['locked_identity'], 'public_keys' => $this->ownerIdentity['public_keys']];
    $input['locked_identity']['nonce'] = F::encode(random_bytes(24));
    $this->putJson('/app/chat/api/identity', $input)->assertOk()->assertJsonPath('data.key_changed_at', null);
    expect($this->conversation->fresh()->rotation_required)->toBeFalse();
    expect(ConversationKeyGrant::count())->toBe(2);
});

it('replacing public keys timestamps the warning and invalidates active keys and recipient grants', function () {
    $identity = F::identity();
    $this->putJson('/app/chat/api/identity', ['locked_identity' => $identity['locked_identity'], 'public_keys' => $identity['public_keys']])
        ->assertOk()->assertJsonPath('data.public_keys', $identity['public_keys']);
    expect(ChatIdentity::where('user_id', $this->owner->id)->first()->key_changed_at)->not->toBeNull();
    expect($this->conversation->fresh()->key_version)->toBe(2);
    expect($this->conversation->fresh()->rotation_required)->toBeTrue();
    expect(ConversationKeyGrant::where('recipient_id', $this->owner->id)->count())->toBe(0);
    Event::assertDispatched(KeyRotated::class, fn ($event) => $event->broadcastWith()['rotation_required'] === true);
});

it('rejects malformed locked identities and mismatched or forged bindings', function (string $field) {
    $input = ['locked_identity' => $this->ownerIdentity['locked_identity'], 'public_keys' => $this->ownerIdentity['public_keys']];
    data_set($input, $field, match ($field) {
        'locked_identity.kdf.memlimit' => 1073741824,
        'locked_identity.version' => '1',
        'locked_identity.private_key' => 'do not store me',
        default => F::encode(random_bytes(str_ends_with($field, 'binding') ? 64 : 32)),
    });
    $this->putJson('/app/chat/api/identity', $input)->assertUnprocessable();
    expect($this->conversation->fresh()->key_version)->toBe(1);
})->with(['locked_identity.kdf.memlimit', 'locked_identity.version', 'locked_identity.private_key',
    'locked_identity.publicKeys.signing', 'public_keys.binding']);

it('returns 403 for all existing conversation endpoints to a nonmember', function () {
    $outsider = User::factory()->create();
    $this->actingAs($outsider);
    foreach (['', '/public-keys', '/messages', '/key-grants'] as $path) {
        $this->getJson($this->base.$path)->assertForbidden();
    }
    $this->postJson($this->base.'/messages', ['envelope' => F::envelope($this->conversation, $this->owner, $this->ownerIdentity)])
        ->assertForbidden();
    $this->patchJson($this->base, ['lifetime_days' => 1])->assertForbidden();
    $this->postJson($this->base.'/members', ['user_id' => $outsider->id])->assertForbidden();
    $this->postJson($this->base.'/rotate', ['key_version' => 1])->assertForbidden();
    $this->getJson('/app/chat/api/conversations')->assertOk()->assertJsonPath('total', 0);
});

it('creates pending direct and group chats with the caller as owner', function (string $type) {
    $this->postJson('/app/chat/api/conversations', ['type' => $type, 'member_ids' => [(string) $this->member->id],
        'lifetime_days' => 7])->assertCreated()->assertJsonPath('data.rotation_required', true)
        ->assertJsonPath('data.key_version', 1)->assertJsonPath('data.created_by', (string) $this->owner->id);
})->with(['direct', 'group']);

it('rejects unavailable users duplicate members and invalid direct membership', function () {
    $unready = User::factory()->create();
    foreach ([[$unready->id], [$this->owner->id], [$this->member->id, $this->member->id],
        [$this->member->id, $unready->id]] as $ids) {
        $this->postJson('/app/chat/api/conversations', ['type' => 'direct', 'member_ids' => $ids, 'lifetime_days' => 7])
            ->assertUnprocessable();
    }
});

it('stores ciphertext unchanged and broadcasts exactly the signed envelope after persistence', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertCreated()
        ->assertJsonPath('data.envelope', $envelope);
    $stored = Message::firstOrFail();
    expect($stored->envelope)->toEqual($envelope);
    expect($stored->expires_at->timestamp)->toBe(strtotime($envelope['expires_at']));
    expect(json_encode($stored->getAttributes()))->not->toContain('private test text', 'plaintext');
    Event::assertDispatched(MessageSent::class, fn ($event) => $event->broadcastWith() === $envelope
        && (string) $event->broadcastOn()[0] === 'private-conversation.'.$this->conversation->id);
});

it('accepts real browser-core identities grants and messages without changing their signed bytes', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/chat-core.json')), true, flags: JSON_THROW_ON_ERROR);
    $this->travelTo(CarbonImmutable::parse($fixture['server_time']));
    $owner = User::factory()->create(['id' => 11]);
    $member = User::factory()->create(['id' => 12]);
    foreach ([[$owner, 'owner'], [$member, 'member']] as [$user, $name]) {
        $this->actingAs($user)->putJson('/app/chat/api/identity', $fixture[$name])->assertOk();
    }
    $conversation = Conversation::create(['id' => 4242, 'type' => 'direct', 'created_by' => $owner->id,
        'lifetime_days' => 7, 'key_version' => 1, 'rotation_required' => true]);
    foreach ([$owner, $member] as $user) {
        ConversationMember::create(['conversation_id' => 4242, 'user_id' => $user->id,
            'role' => $user->id === 11 ? 'owner' : 'member', 'joined_at' => now()]);
    }
    $base = '/app/chat/api/conversations/4242';
    $this->actingAs($owner)->postJson($base.'/key-grants', ['key_version' => 1, 'grants' => $fixture['grants']])->assertOk();
    $this->postJson($base.'/rotate', ['key_version' => 1])->assertOk();
    $this->postJson($base.'/messages', ['envelope' => $fixture['envelope']])->assertCreated()
        ->assertJsonPath('data.envelope', $fixture['envelope']);
    $this->travelBack();
});

it('rejects forbidden data and invalid signed envelope fields', function (string $field) {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $envelope[$field] = match ($field) {
        'plaintext' => 'secret content', 'sender_id' => (string) $this->member->id,
        'conversation_id' => '99999', 'v' => '1', 'key_version' => '1',
        'ciphertext' => F::encode(random_bytes(6001)), 'nonce' => str_repeat('A', 31),
        'signature' => F::encode(random_bytes(64)), 'expires_at' => null,
    };
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertUnprocessable();
    expect(Message::count())->toBe(0);
    Event::assertNotDispatched(MessageSent::class);
})->with(['plaintext', 'sender_id', 'conversation_id', 'v', 'key_version', 'ciphertext', 'nonce', 'signature', 'expires_at']);

it('rejects plaintext alongside a valid envelope without flashing chat data to session', function () {
    $this->postJson($this->base.'/messages', ['plaintext' => 'secret', 'envelope' => F::envelope($this->conversation, $this->owner, $this->ownerIdentity)])
        ->assertUnprocessable()->assertSessionMissing('_old_input');
    expect(Message::count())->toBe(0);
});

it('enforces signed expiry lifetime and clock skew without rewriting it', function () {
    foreach ([now()->subSecond(), now()->addDays(30), now()->addDays(7)->addSeconds(121)] as $time) {
        $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity, $time->utc()->format('Y-m-d\\TH:i:s\\Z'));
        $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertUnprocessable();
    }
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity,
        now()->addDays(7)->subSeconds(119)->utc()->format('Y-m-d\\TH:i:s\\Z'));
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertCreated()->assertJsonPath('data.envelope', $envelope);
});

it('blocks pending and stale-version sends', function () {
    $this->conversation->update(['rotation_required' => true]);
    $this->postJson($this->base.'/messages', ['envelope' => F::envelope($this->conversation, $this->owner, $this->ownerIdentity)])
        ->assertConflict();
    $this->conversation->update(['rotation_required' => false]);
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $this->conversation->increment('key_version');
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertConflict();
});

it('never serves expired rows and prunes them independently of history queries', function () {
    foreach ([now()->subSecond(), now(), now()->addHour()] as $expiry) {
        Message::create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->owner->id,
            'key_version' => 1, 'envelope' => ['ciphertext' => 'opaque'], 'expires_at' => $expiry, 'created_at' => now()]);
    }
    $this->getJson($this->base.'/messages')->assertOk()->assertJsonCount(1, 'data');
    $this->artisan('model:prune', ['--model' => [Message::class]])->assertSuccessful();
    expect(Message::count())->toBe(1);
});

it('paginates unexpired history with a stable cursor and join boundary', function () {
    for ($i = 0; $i < 52; $i++) {
        Message::create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->owner->id,
            'key_version' => 1, 'envelope' => ['ciphertext' => 'opaque'], 'expires_at' => now()->addDay(), 'created_at' => now()]);
    }
    Message::create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->owner->id,
        'key_version' => 1, 'envelope' => ['ciphertext' => 'before join'], 'expires_at' => now()->addDay(), 'created_at' => now()->subHour()]);
    $first = $this->getJson($this->base.'/messages')->assertOk()->assertJsonCount(50, 'data');
    $cursor = $first->json('next_before_id');
    $this->getJson($this->base.'/messages?before_id='.$cursor)->assertJsonCount(2, 'data')->assertJsonPath('next_before_id', null);
});

it('restricts grant retrieval and all mutations to the appropriate member role', function () {
    $this->actingAs($this->member)->getJson($this->base.'/key-grants')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recipient_id', (string) $this->member->id);
    $this->patchJson($this->base, ['lifetime_days' => 30])->assertForbidden();
    $this->postJson($this->base.'/members', ['user_id' => $this->owner->id])->assertForbidden();
    $this->postJson($this->base.'/rotate', ['key_version' => 1])->assertForbidden();
});

it('rotates on add remove and rejoin while deleting old grants for the returning member', function () {
    [$new] = F::user();
    $this->postJson($this->base.'/members', ['user_id' => $new->id])->assertOk()
        ->assertJsonPath('data.key_version', 2)->assertJsonPath('data.rotation_required', true);
    $this->deleteJson($this->base.'/members/'.$this->member->id)->assertOk()->assertJsonPath('data.key_version', 3);
    $this->actingAs($this->member)->getJson($this->base.'/key-grants')->assertForbidden();
    $this->actingAs($this->owner)->postJson($this->base.'/members', ['user_id' => $this->member->id])
        ->assertOk()->assertJsonPath('data.key_version', 4);
    expect(ConversationKeyGrant::where('recipient_id', $this->member->id)->count())->toBe(0);
    $this->deleteJson($this->base.'/members/'.$this->owner->id)->assertUnprocessable();
});

it('activates only a complete valid owner-signed batch for the current version', function () {
    $this->conversation->update(['rotation_required' => true]);
    ConversationKeyGrant::query()->delete();
    $grants = [F::grant($this->conversation, $this->owner, $this->ownerIdentity, $this->owner),
        F::grant($this->conversation, $this->owner, $this->ownerIdentity, $this->member)];
    $this->postJson($this->base.'/rotate', ['key_version' => 1])->assertConflict();
    $this->postJson($this->base.'/key-grants', ['key_version' => 1, 'grants' => [$grants[0]]])->assertUnprocessable();
    $bad = $grants;
    $bad[1]['signature'] = F::encode(random_bytes(64));
    $this->postJson($this->base.'/key-grants', ['key_version' => 1, 'grants' => $bad])->assertUnprocessable();
    expect(ConversationKeyGrant::count())->toBe(0);
    $this->actingAs($this->member)->postJson($this->base.'/key-grants', ['key_version' => 1, 'grants' => $grants])->assertForbidden();
    $this->actingAs($this->owner)->postJson($this->base.'/key-grants', ['key_version' => 1, 'grants' => $grants])->assertOk();
    $this->postJson($this->base.'/rotate', ['key_version' => 1])->assertOk()->assertJsonPath('data.rotation_required', false);
    $this->postJson($this->base.'/key-grants', ['key_version' => 1, 'grants' => $grants])->assertConflict();
    $this->postJson($this->base.'/rotate', ['key_version' => 2])->assertConflict();
    Event::assertDispatched(KeyRotated::class, fn ($event) => $event->broadcastWith()['rotation_required'] === false);
});

it('changes lifetime only for future messages', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertCreated();
    $this->patchJson($this->base, ['lifetime_days' => 1, 'title' => 'Friends'])->assertOk();
    expect(Message::first()->envelope['expires_at'])->toBe($envelope['expires_at']);
    $this->postJson($this->base.'/messages', ['envelope' => F::envelope($this->conversation->fresh(), $this->owner, $this->ownerIdentity)])
        ->assertCreated();
});

it('authorizes only active verified members on the private Pusher channel', function () {
    config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret', 'broadcasting.connections.pusher.app_id' => '123']);
    require base_path('routes/channels.php');
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-conversation.'.$this->conversation->id];
    $this->postJson('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(User::factory()->create())->postJson('/broadcasting/auth', $payload)->assertForbidden();
    ConversationMember::where('user_id', $this->member->id)->update(['left_at' => now()]);
    $this->actingAs($this->member)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create())->postJson('/broadcasting/auth', $payload)->assertForbidden();
    expect(app('router')->getRoutes()->getByName('broadcasting.auth')->gatherMiddleware())->toContain('web', ChatResponse::class, 'auth', 'verified');
});

it('rate limits messages identities and general API independently', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    for ($i = 0; $i < 30; $i++) {
        $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertCreated();
    }
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertTooManyRequests();
    $this->getJson('/app/chat/api/identity')->assertOk();
    $input = ['locked_identity' => $this->ownerIdentity['locked_identity'], 'public_keys' => $this->ownerIdentity['public_keys']];
    for ($i = 0; $i < 5; $i++) {
        $this->putJson('/app/chat/api/identity', $input)->assertOk();
    }
    $this->putJson('/app/chat/api/identity', $input)->assertTooManyRequests();
});

it('enforces the general account API rate limit', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->getJson('/app/chat/api/identity')->assertOk();
    }
    $this->getJson('/app/chat/api/identity')->assertTooManyRequests();
});

it('cascades account-owned chat data and rotates surviving conversations on deletion', function () {
    $owned = F::conversation($this->member, $this->memberIdentity, [$this->owner]);
    DB::transaction(fn () => $this->member->delete());
    expect(Conversation::find($owned->id))->toBeNull();
    expect(ChatIdentity::where('user_id', $this->member->id)->exists())->toBeFalse();
    expect(ConversationKeyGrant::where('recipient_id', $this->member->id)->exists())->toBeFalse();
    expect($this->conversation->fresh()->rotation_required)->toBeTrue();
    expect($this->conversation->fresh()->key_version)->toBe(2);
});

it('does not broadcast rolled back messages and retains committed messages on transport failure', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    DB::beginTransaction();
    app(SendMessage::class)->handle($this->conversation, $this->owner, $envelope);
    DB::rollBack();
    Event::assertNotDispatched(MessageSent::class);
    expect(Message::count())->toBe(0);
    $dispatcher = Event::getFacadeRoot();
    Event::shouldReceive('dispatch')
        ->andReturnUsing(function ($event, $payload = [], $halt = false) use ($dispatcher) {
            if ($event instanceof MessageSent) {
                throw new RuntimeException('transport failure');
            }

            return $dispatcher->dispatch($event, $payload, $halt);
        });
    Log::shouldReceive('warning')->once()->with('Chat broadcast failed.', Mockery::on(fn ($data) => array_keys($data) === ['event', 'error_type']));
    $this->postJson($this->base.'/messages', ['envelope' => $envelope])->assertCreated();
    expect(Message::count())->toBe(1);
});

it('accepts only the production kdf costs for locked identities', function () {
    $input = [
        'locked_identity' => $this->ownerIdentity['locked_identity'],
        'public_keys' => $this->ownerIdentity['public_keys'],
    ];

    $tooHighOps = $input;
    $tooHighOps['locked_identity']['kdf']['opslimit'] = 4;
    $this->putJson('/app/chat/api/identity', $tooHighOps)->assertUnprocessable();

    $tooLowOps = $input;
    $tooLowOps['locked_identity']['kdf']['opslimit'] = 1;
    $this->putJson('/app/chat/api/identity', $tooLowOps)->assertUnprocessable();

    $tooHighMem = $input;
    $tooHighMem['locked_identity']['kdf']['memlimit'] = 268435457;
    $this->putJson('/app/chat/api/identity', $tooHighMem)->assertUnprocessable();

    $tooLowMem = $input;
    $tooLowMem['locked_identity']['kdf']['memlimit'] = 67108864;
    $this->putJson('/app/chat/api/identity', $tooLowMem)->assertUnprocessable();

    $valid = $input;
    $valid['locked_identity']['kdf']['opslimit'] = 3;
    $valid['locked_identity']['kdf']['memlimit'] = 268435456;
    $this->putJson('/app/chat/api/identity', $valid)->assertOk();
});

it('allows active member to report message opening moderation case with verified envelope and disclaimer', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $payload = [
        'envelope' => $envelope,
        'plaintext' => 'This is the decrypted harassment message',
        'reason' => 'Insulting and harassing language',
    ];

    $response = $this->actingAs($this->member)
        ->postJson($this->base.'/reports', $payload)
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'conversation_id', 'created_at', 'status']])
        ->assertJsonPath('data.conversation_id', (string) $this->conversation->id)
        ->assertJsonPath('data.status', 'received');

    $reportId = (int) $response->json('data.id');
    $report = ChatReport::findOrFail($reportId);

    expect($report->conversation_id)->toBe($this->conversation->id)
        ->and($report->reporter_id)->toBe($this->member->id)
        ->and($report->sender_id)->toBe($this->owner->id)
        ->and($report->plaintext)->toBe('This is the decrypted harassment message')
        ->and($report->reason)->toBe('Insulting and harassing language')
        ->and($report->verification_note)->toBe('reported by participant, not verifiable by the server')
        ->and($report->envelope)->toEqual($envelope);

    $case = ModerationCase::where('source_type', 'chat_report')->where('source_id', $report->id)->first();
    expect($case)->not->toBeNull()
        ->and($case->subject_user_id)->toBe($this->owner->id)
        ->and($case->status)->toBe(CaseStatus::Open)
        ->and($case->priority)->toBe(Priority::Normal);

    expect($case->events()->where('type', EventType::Opened)->exists())->toBeTrue();
    expect($case->events()->first()->data['source'])->toBe('chat_report');
});

it('rejects reports from non-members with 403', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $payload = [
        'envelope' => $envelope,
        'plaintext' => 'Some text',
        'reason' => 'Some reason',
    ];

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->postJson($this->base.'/reports', $payload)->assertForbidden();

    ConversationMember::where('user_id', $this->member->id)->update(['left_at' => now()]);
    $this->actingAs($this->member)->postJson($this->base.'/reports', $payload)->assertForbidden();
});

it('rejects reports with foreign or tampered envelopes with 422', function () {
    [$otherOwner, $otherIdentity] = F::user();
    $otherConv = F::conversation($otherOwner, $otherIdentity, []);
    $foreignEnvelope = F::envelope($otherConv, $otherOwner, $otherIdentity);

    $payload = [
        'envelope' => $foreignEnvelope,
        'plaintext' => 'Foreign text',
        'reason' => 'Foreign message',
    ];

    // Foreign conversation_id
    $this->actingAs($this->member)->postJson($this->base.'/reports', $payload)->assertUnprocessable();

    // Tampered ciphertext
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $tampered = $envelope;
    $tampered['ciphertext'] = F::encode(random_bytes(32));
    $this->actingAs($this->member)->postJson($this->base.'/reports', [
        'envelope' => $tampered,
        'plaintext' => 'Tampered text',
        'reason' => 'Tampered message',
    ])->assertUnprocessable();

    // Sender not a member of conversation
    $stranger = User::factory()->create();
    $strangerIdentity = F::identity();
    ChatIdentity::create(['user_id' => $stranger->id, 'locked_identity' => $strangerIdentity['locked_identity'], 'public_keys' => $strangerIdentity['public_keys']]);
    $strangerEnvelope = F::envelope($this->conversation, $stranger, $strangerIdentity);
    $this->actingAs($this->member)->postJson($this->base.'/reports', [
        'envelope' => $strangerEnvelope,
        'plaintext' => 'Stranger text',
        'reason' => 'Stranger message',
    ])->assertUnprocessable();
});

it('rate limits chat message reports to 5 per minute', function () {
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $payload = [
        'envelope' => $envelope,
        'plaintext' => 'Spamming reports',
        'reason' => 'Report budget test',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->member)->postJson($this->base.'/reports', $payload)->assertCreated();
    }
    $this->actingAs($this->member)->postJson($this->base.'/reports', $payload)->assertTooManyRequests();
});

it('does not expose any other messages when viewing moderation case', function () {
    for ($i = 0; $i < 5; $i++) {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->owner->id,
            'key_version' => 1,
            'envelope' => ['ciphertext' => 'secret_message_'.$i],
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
        ]);
    }

    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $this->actingAs($this->member)->postJson($this->base.'/reports', [
        'envelope' => $envelope,
        'plaintext' => 'Only this message was reported',
        'reason' => 'Inappropriate text',
    ])->assertCreated();

    $case = ModerationCase::where('source_type', 'chat_report')->firstOrFail();
    $source = $case->source;
    expect($source)->toBeInstanceOf(ChatReport::class)
        ->and($source->plaintext)->toBe('Only this message was reported')
        ->and($source->verification_note)->toBe('reported by participant, not verifiable by the server');

    // Verify model attributes and serialization expose only the single reported record
    $serialized = $case->toArray();
    expect(json_encode($serialized))->not->toContain('secret_message_');
    expect($case->relationLoaded('messages'))->toBeFalse();
});

it('prunes chat reports following records retention only after case is pruned', function () {
    config(['oasis.privacy.records_years' => 2]);
    $envelope = F::envelope($this->conversation, $this->owner, $this->ownerIdentity);
    $report = ChatReport::create([
        'conversation_id' => $this->conversation->id,
        'reporter_id' => $this->member->id,
        'sender_id' => $this->owner->id,
        'envelope' => $envelope,
        'plaintext' => 'Old message',
        'reason' => 'Old reason',
        'verification_note' => 'reported by participant, not verifiable by the server',
    ]);
    $report->forceFill(['created_at' => now()->subYears(3)])->save();

    // ModerationCase still exists
    $case = ModerationCase::where('source_type', 'chat_report')->where('source_id', $report->id)->firstOrFail();
    $this->artisan('model:prune', ['--model' => [ChatReport::class]])->assertSuccessful();
    expect(ChatReport::find($report->id))->not->toBeNull();

    // After case is deleted
    $case->delete();
    $this->artisan('model:prune', ['--model' => [ChatReport::class]])->assertSuccessful();
    expect(ChatReport::find($report->id))->toBeNull();
});
