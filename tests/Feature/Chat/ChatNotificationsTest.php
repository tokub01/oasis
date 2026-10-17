<?php

/* Chat-Benachrichtigungen (docs/oasis-chat.md): unread count, read marker, opt-in e-mail/push, metadata only. */

use App\Events\Chat\KeyRotated;
use App\Events\Chat\MessageSent;
use App\Livewire\Privacy\Center;
use App\Mail\Chat\NewChatMessage;
use App\Models\Block;
use App\Models\Notifications\NotificationPreference;
use App\Models\PrivacySetting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\ChatFixtures as F;

beforeEach(function () {
    $this->withoutVite();
    Event::fake([MessageSent::class, KeyRotated::class]);
    Mail::fake();
    [$this->owner, $this->ownerIdentity] = F::user();
    [$this->member, $this->memberIdentity] = F::user();
    $this->conversation = F::conversation($this->owner, $this->ownerIdentity, [$this->member]);
    $this->base = '/app/chat/api/conversations/'.$this->conversation->id;
});

function sendAs($test, $sender, array $identity): void
{
    $test->actingAs($sender)->postJson($test->base.'/messages', ['envelope' => F::envelope($test->conversation, $sender, $identity)])->assertCreated();
}

it('counts chats with new messages from others until the member opens them', function () {
    sendAs($this, $this->owner, $this->ownerIdentity);

    $this->actingAs($this->owner)->getJson('/app/chat/api/unread')->assertOk()->assertJson(['count' => 0]);
    $this->actingAs($this->member)->getJson('/app/chat/api/unread')->assertOk()
        ->assertJson(['count' => 1, 'conversation_ids' => [(string) $this->conversation->id]]);
    $this->getJson('/app/chat/api/conversations')->assertJsonPath('data.0.unread', true);
    $this->get(route('app.home'))->assertSee('data-chat-count>1<', false);

    $this->travel(1)->seconds();
    $this->postJson($this->base.'/read')->assertOk()->assertJson(['count' => 0]);
    $this->getJson('/app/chat/api/unread')->assertJson(['count' => 0]);
    $this->getJson('/app/chat/api/conversations')->assertJsonPath('data.0.unread', false);
});

it('ignores messages from blocked people and refuses the read marker to outsiders', function () {
    sendAs($this, $this->owner, $this->ownerIdentity);
    Block::create(['user_id' => $this->member->id, 'blocked_user_id' => $this->owner->id]);

    $this->actingAs($this->member)->getJson('/app/chat/api/unread')->assertJson(['count' => 0]);
    [$outsider] = F::user();
    $this->actingAs($outsider)->postJson($this->base.'/read')->assertForbidden();
});

it('sends no e-mail by default', function () {
    sendAs($this, $this->owner, $this->ownerIdentity);

    Mail::assertNothingSent();
});

it('sends one content-free e-mail per unread chat when switched on, never during quiet hours', function () {
    $this->member->privacySetting()->create(['chat_email' => true]);

    sendAs($this, $this->owner, $this->ownerIdentity);
    sendAs($this, $this->owner, $this->ownerIdentity);
    Mail::assertSent(NewChatMessage::class, 1);
    Mail::assertSent(NewChatMessage::class, fn ($mail) => $mail->hasTo($this->member->email)
        && ! str_contains($mail->render(), $this->owner->display_name) && ! str_contains($mail->render(), 'private test text'));

    // Read, then a new message: one more e-mail. During a pause: none.
    $this->travel(1)->seconds();
    $this->actingAs($this->member)->postJson($this->base.'/read')->assertOk();
    $this->travel(1)->seconds();
    NotificationPreference::create(['user_id' => $this->member->id, 'paused_until' => now()->addDay()]);
    sendAs($this, $this->owner, $this->ownerIdentity);
    Mail::assertSent(NewChatMessage::class, 1);

    NotificationPreference::where('user_id', $this->member->id)->update(['paused_until' => null]);
    sendAs($this, $this->owner, $this->ownerIdentity);
    Mail::assertSent(NewChatMessage::class, 2);
});

it('offers the chat switches in the privacy center, off by default with a hint', function () {
    expect((new PrivacySetting)->chat_email)->toBeFalse()->and((new PrivacySetting)->chat_push)->toBeFalse();

    Livewire::actingAs($this->member)->test(Center::class)
        ->assertSee('E-Mail bei neuen Chat-Nachrichten')->assertSee('Standardmäßig aus – schalte sie ein');
});
