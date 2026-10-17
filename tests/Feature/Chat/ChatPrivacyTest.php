<?php

use App\Events\Chat\KeyRotated;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Follow;
use Illuminate\Support\Facades\Event;
use Tests\Support\ChatFixtures as F;

it('enforces chat invite audiences on creation and exact username lookup', function (string $audience, bool $allowed) {
    [$owner] = F::user();
    [$target] = F::user();
    $target->privacySetting()->create(['chat_invites_audience' => $audience]);

    $this->actingAs($owner)->getJson('/app/chat/api/users?username='.$target->username)
        ->assertOk()->assertJsonCount($allowed ? 1 : 0, 'data');
    $response = $this->postJson('/app/chat/api/conversations', ['type' => 'direct', 'member_ids' => [$target->id], 'lifetime_days' => 7]);
    if ($allowed) {
        $response->assertCreated();
        expect(Conversation::count())->toBe(1);
    } else {
        $response->assertForbidden()->assertJsonPath('message', 'Diese Person erlaubt dir keine Chat-Einladungen.');
        expect(Conversation::count())->toBe(0);
    }
})->with(['everyone' => ['everyone', true], 'members' => ['members', true], 'following' => ['following', false], 'nobody' => ['nobody', false]]);

it('allows an invitation from a person the recipient follows and ignores searchable', function () {
    [$owner] = F::user();
    [$target] = F::user();
    $target->privacySetting()->create(['chat_invites_audience' => 'following', 'searchable' => false]);
    Follow::create(['follower_id' => $target->id, 'followable_type' => $owner->getMorphClass(), 'followable_id' => $owner->id]);

    $this->actingAs($owner)->getJson('/app/chat/api/users?username='.$target->username)->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/app/chat/api/conversations', ['type' => 'direct', 'member_ids' => [$target->id], 'lifetime_days' => 7])->assertCreated();
});

it('rechecks invitation settings when adding or readding a member but preserves existing chats', function () {
    [$owner, $identity] = F::user();
    [$member] = F::user();
    [$target] = F::user();
    $conversation = F::conversation($owner, $identity, [$member]);
    $target->privacySetting()->create(['chat_invites_audience' => 'nobody']);
    $member->privacySetting()->create(['chat_invites_audience' => 'nobody']);
    Event::fake([KeyRotated::class]);

    $this->actingAs($owner)->getJson('/app/chat/api/conversations/'.$conversation->id)->assertOk();
    $this->postJson('/app/chat/api/conversations/'.$conversation->id.'/members', ['user_id' => $target->id])
        ->assertForbidden()->assertJsonPath('message', 'Diese Person erlaubt dir keine Chat-Einladungen.');
    $this->assertDatabaseMissing('conversation_members', ['conversation_id' => $conversation->id, 'user_id' => $target->id]);
    expect($conversation->fresh()->key_version)->toBe(1);
    Event::assertNotDispatched(KeyRotated::class);
});

it('keeps bidirectional blocks above a permissive invitation audience', function (bool $reverse) {
    [$owner] = F::user();
    [$target] = F::user();
    $target->privacySetting()->create(['chat_invites_audience' => 'everyone']);
    Block::create(['user_id' => $reverse ? $target->id : $owner->id, 'blocked_user_id' => $reverse ? $owner->id : $target->id]);

    $this->actingAs($owner)->getJson('/app/chat/api/users?username='.$target->username)->assertOk()->assertJsonCount(0, 'data');
    $this->postJson('/app/chat/api/conversations', ['type' => 'direct', 'member_ids' => [$target->id], 'lifetime_days' => 7])->assertUnprocessable();
    expect(Conversation::count())->toBe(0);
})->with([false, true]);
