<?php

use App\Models\ConversationMember;
use Illuminate\Support\Facades\Broadcast;
use Tests\Support\ChatFixtures as Chat;

it('signs private channel access with the configured Reverb key and secret', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'crosscheck-key',
        'broadcasting.connections.reverb.secret' => 'crosscheck-secret', 'broadcasting.connections.reverb.app_id' => 'crosscheck']);
    Broadcast::purge('reverb');
    require base_path('routes/channels.php');
    [$owner, $keys] = Chat::user();
    [$member] = Chat::user();
    $conversation = Chat::conversation($owner, $keys, [$member]);
    $channel = 'private-conversation.'.$conversation->id;
    $this->actingAs($member)->postJson('/broadcasting/auth', ['socket_id' => '42.17', 'channel_name' => $channel])
        ->assertOk()->assertExactJson(['auth' => 'crosscheck-key:'.hash_hmac('sha256', '42.17:'.$channel, 'crosscheck-secret')]);
    ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $member->id)->update(['left_at' => now()]);
    $this->postJson('/broadcasting/auth', ['socket_id' => '42.17', 'channel_name' => $channel])->assertForbidden();
});

it('allows only the configured WebSocket origin in CSP for custom TLS ports', function () {
    config(['reverb.apps.apps.0.options.host' => 'chat.example.tld', 'reverb.apps.apps.0.options.scheme' => 'https',
        'reverb.apps.apps.0.options.port' => 8443]);
    $response = $this->get(route('oasis.home'))->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toContain("connect-src 'self' wss://chat.example.tld:8443")
        ->not->toContain('pusher.com');
});

it('does not name an external realtime processor in the privacy policy', function () {
    $this->get(route('oasis.datenschutz'))->assertOk()->assertDontSee('Pusher');
    expect(config('oasis.privacy.realtime_provider'))->toBeNull();
});
