<?php

/** Local E2E fixture lifecycle. Only accounts belonging to the supplied random run may be touched. */
use App\Actions\Account\DeleteAccount;
use App\Events\Chat\MessageSent;
use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';

try {
    $app->make(Kernel::class)->bootstrap();
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! $app->environment('local') || ! preg_match('/^[a-f0-9]{20}$/D', $input['run'] ?? '')) {
        throw new RuntimeException('This fixture requires a local environment and a valid run identifier.');
    }
    $emails = array_map(fn (string $name): string => 'chat-e2e-'.$input['run'].'-'.$name.'@demo.oasis.tld',
        ['mira', 'jonas', 'outsider']);
    $users = User::whereIn('email', $emails)->get();
    $ids = $users->modelKeys();

    $result = match ($input['operation']) {
        'setup' => DB::transaction(function () use ($input, $emails): array {
            $accounts = [];
            foreach (['mira', 'jonas', 'outsider'] as $index => $name) {
                $user = User::factory()->create([
                    'email' => $emails[$index],
                    'username' => 'chate2e_'.substr($input['run'], 0, 12).'_'.$name,
                    'display_name' => 'Chat-E2E '.ucfirst($name),
                    'password' => $input['password'],
                    'email_verified_at' => now(),
                    // Two days old: these accounts test privacy and chat, not the limits of new accounts.
                    'created_at' => now()->subDays(2),
                    'trial_ends_at' => now()->addDays(30),
                ]);
                $accounts[$name] = ['id' => (string) $user->id, 'email' => $user->email, 'username' => $user->username];
            }

            return ['accounts' => $accounts, 'reverb_key' => config('broadcasting.connections.reverb.key'),
                'broadcast_driver' => config('broadcasting.default'), 'queue_connection' => config('queue.default'),
                'broadcast_now' => is_subclass_of(MessageSent::class, ShouldBroadcastNow::class)];
        }),
        'inspect' => (function () use ($input, $ids): array {
            $conversation = Conversation::whereKey($input['conversation'])->whereIn('created_by', $ids)->firstOrFail();
            $message = Message::where('conversation_id', $conversation->id)->sole();

            return ['envelope_json' => $message->getRawOriginal('envelope'),
                'expires_at' => $message->expires_at->toISOString(),
                'identity_count' => ChatIdentity::whereIn('user_id', $ids)->count(),
                'grant_count' => ConversationKeyGrant::where('conversation_id', $conversation->id)->count()];
        })(),
        'cleanup' => (function () use ($users, $ids, $emails): array {
            $conversations = Conversation::whereIn('created_by', $ids)->pluck('id')->all();
            foreach ($users as $user) {
                app(DeleteAccount::class)->handle($user);
            }

            return ['deleted_users' => count($ids), 'remaining' => [
                'users' => User::whereIn('email', $emails)->count(),
                'identities' => ChatIdentity::whereIn('user_id', $ids)->count(),
                'conversations' => Conversation::whereIn('id', $conversations)->count(),
                'memberships' => ConversationMember::whereIn('conversation_id', $conversations)->count(),
                'messages' => Message::whereIn('conversation_id', $conversations)->count(),
                'grants' => ConversationKeyGrant::whereIn('conversation_id', $conversations)->count(),
                'sessions' => DB::table('sessions')->whereIn('user_id', $ids)->count(),
            ]];
        })(),
        default => throw new InvalidArgumentException('Unknown fixture operation.'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Request/cookie/identity bodies and connection credentials must never enter test output.
    fwrite(STDERR, 'Chat E2E fixture failed: '.$exception::class.PHP_EOL);
    exit(1);
}
