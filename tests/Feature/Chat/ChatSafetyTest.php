<?php

/* Schutz im Chat (docs/oasis-chat-client.md): block and leave from the safety banner, word lists for the browser. */

use App\Events\Chat\KeyRotated;
use App\Events\Chat\MessageSent;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\ConversationMember;
use Illuminate\Support\Facades\Event;
use Tests\Support\ChatFixtures as F;

beforeEach(function () {
    $this->withoutVite();
    Event::fake([MessageSent::class, KeyRotated::class]);
    [$this->owner, $this->ownerIdentity] = F::user();
    [$this->member, $this->memberIdentity] = F::user();
    $this->conversation = F::conversation($this->owner, $this->ownerIdentity, [$this->member]);
    $this->base = '/app/chat/api/conversations/'.$this->conversation->id;
});

it('lets a member block the sender and leave the chat in one go', function () {
    $this->actingAs($this->member)->postJson('/app/chat/api/blocks', ['user_id' => $this->owner->id])->assertOk();
    $this->postJson($this->base.'/leave')->assertOk();

    expect(Block::where('user_id', $this->member->id)->where('blocked_user_id', $this->owner->id)->exists())->toBeTrue()
        ->and(ConversationMember::where('user_id', $this->member->id)->first()->left_at)->not->toBeNull()
        ->and(Conversation::find($this->conversation->id)->rotation_required)->toBeTrue();
    $this->getJson('/app/chat/api/conversations')->assertJsonCount(0, 'data');
    $this->getJson($this->base)->assertForbidden();
});

it('keeps the owner of a group in it and refuses to block oneself', function () {
    $this->actingAs($this->owner)->postJson($this->base.'/leave')->assertUnprocessable();
    $this->postJson('/app/chat/api/blocks', ['user_id' => $this->owner->id])->assertUnprocessable();
});

it('gives the chat page the shared word lists for the checks in the browser', function () {
    $this->actingAs($this->member)->get(route('app.chat'))->assertOk()
        ->assertSee('data-safety=', false)->assertSee('arschloch', false)->assertSee('digga', false);
});
