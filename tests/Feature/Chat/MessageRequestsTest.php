<?php

/* Nachrichtenanfragen (docs/oasis-chat.md): chats from strangers stay quiet requests until accepted or answered. */

use App\Actions\Chat\SendMessage;
use App\Events\Chat\KeyRotated;
use App\Events\Chat\MessageSent;
use App\Models\ConversationMember;
use App\Models\Follow;
use App\Models\Friendship;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ChatFixtures as F;

beforeEach(function () {
    Event::fake([MessageSent::class, KeyRotated::class]);
    Mail::fake();
});

function startChat($test, $owner, $other): int
{
    return (int) $test->actingAs($owner)->postJson('/app/chat/api/conversations', ['type' => 'direct', 'member_ids' => [$other->id], 'lifetime_days' => 7])
        ->assertCreated()->json('data.id');
}

it('makes a chat from a stranger a request, and a chat from someone followed or a friend a normal chat', function () {
    [$owner] = F::user();
    [$stranger] = F::user();
    [$follower] = F::user();
    [$friend] = F::user();
    Follow::create(['follower_id' => $follower->id, 'followable_type' => $owner->getMorphClass(), 'followable_id' => $owner->id]);
    Friendship::create(['requester_id' => $owner->id, 'addressee_id' => $friend->id, 'accepted_at' => now()]);

    $requestId = startChat($this, $owner, $stranger);
    startChat($this, $owner, $follower);
    startChat($this, $owner, $friend);

    expect(ConversationMember::where('user_id', $stranger->id)->sole()->accepted_at)->toBeNull()
        ->and(ConversationMember::where('user_id', $follower->id)->sole()->accepted_at)->not->toBeNull()
        ->and(ConversationMember::where('user_id', $friend->id)->sole()->accepted_at)->not->toBeNull();
    $this->actingAs($stranger)->getJson('/app/chat/api/conversations')->assertJsonPath('data.0.request', true);
    $this->getJson('/app/chat/api/conversations/'.$requestId)->assertJsonPath('data.request', true);
    $this->postJson('/app/chat/api/conversations/'.$requestId.'/accept')->assertOk();
    $this->getJson('/app/chat/api/conversations/'.$requestId)->assertJsonPath('data.request', false);
});

it('keeps requests out of the count and notifications, limits the sender and accepts on reply', function () {
    [$owner, $ownerIdentity] = F::user();
    [$member, $memberIdentity] = F::user();
    $member->privacySetting()->create(['chat_email' => true]);
    $conversation = F::conversation($owner, $ownerIdentity, [$member]);
    $conversation->update(['type' => 'direct']);
    ConversationMember::where('user_id', $member->id)->update(['accepted_at' => null]);
    $base = '/app/chat/api/conversations/'.$conversation->id;

    foreach (range(1, SendMessage::REQUEST_MESSAGE_LIMIT) as $ignored) {
        $this->actingAs($owner)->postJson($base.'/messages', ['envelope' => F::envelope($conversation, $owner, $ownerIdentity)])->assertCreated();
    }
    $this->postJson($base.'/messages', ['envelope' => F::envelope($conversation, $owner, $ownerIdentity)])->assertStatus(429);
    $this->actingAs($member)->getJson('/app/chat/api/unread')->assertJson(['count' => 0, 'requests' => 1]);
    Mail::assertNothingSent();

    $this->postJson($base.'/messages', ['envelope' => F::envelope($conversation, $member, $memberIdentity)])->assertCreated();
    expect(ConversationMember::where('user_id', $member->id)->sole()->accepted_at)->not->toBeNull();
    $this->actingAs($owner)->postJson($base.'/messages', ['envelope' => F::envelope($conversation, $owner, $ownerIdentity)])->assertCreated();
});

it('lets members change who may write to them and friend requests right in the chat', function () {
    [$member] = F::user();

    $this->actingAs($member)->getJson('/app/chat/api/settings')->assertOk()
        ->assertJson(['chat_invites_audience' => 'members', 'friend_requests' => true])
        ->assertJsonPath('choices.contacts', 'Freund:innen und Menschen, denen du folgst');
    $this->patchJson('/app/chat/api/settings', ['key' => 'chat_invites_audience', 'value' => 'contacts'])->assertOk()
        ->assertJson(['chat_invites_audience' => 'contacts']);
    $this->patchJson('/app/chat/api/settings', ['key' => 'friend_requests', 'value' => false])->assertOk()->assertJson(['friend_requests' => false]);
    $this->patchJson('/app/chat/api/settings', ['key' => 'search_engines', 'value' => true])->assertUnprocessable();
});
