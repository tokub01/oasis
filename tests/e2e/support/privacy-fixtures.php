<?php

/**
 * Local privacy E2E fixture lifecycle (tests/e2e/privacy.e2e.mjs). Only the three accounts of the supplied random run
 * may be touched; everything else is merely counted, so the test can prove that demo accounts stay unchanged.
 */
use App\Actions\Account\DeleteAccount;
use App\Models\Block;
use App\Models\ChatIdentity;
use App\Models\ContentCheck;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Follow;
use App\Models\Post;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';

const PRIVACY_E2E_ROLES = ['owner', 'stranger', 'followed'];

/** Counts and a digest of all data that does not belong to a privacy E2E run (demo accounts and their content). */
function privacyE2eForeignState(): array
{
    $foreign = User::where('email', 'not like', 'privacy-e2e-%')->pluck('id');

    return [
        'users' => $foreign->count(),
        'users_digest' => md5(User::whereIn('id', $foreign)->orderBy('id')->get(['id', 'email', 'username', 'updated_at'])->toJson()),
        'privacy_digest' => md5(PrivacySetting::whereIn('user_id', $foreign)->orderBy('user_id')->get()->toJson()),
        'posts' => Post::whereIn('user_id', $foreign)->count(),
        'notifications' => DB::table('notifications')->whereIn('notifiable_id', $foreign)->count(),
        'follows' => Follow::whereIn('follower_id', $foreign)->count(),
        'blocks' => Block::whereIn('user_id', $foreign)->count(),
        'conversations' => Conversation::whereIn('created_by', $foreign)->count(),
    ];
}

try {
    $app->make(Kernel::class)->bootstrap();
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! $app->environment('local') || ! preg_match('/^[a-f0-9]{20}$/D', $input['run'] ?? '')) {
        throw new RuntimeException('This fixture requires a local environment and a valid run identifier.');
    }
    $emails = array_map(fn (string $role): string => 'privacy-e2e-'.$input['run'].'-'.$role.'@demo.oasis.tld', PRIVACY_E2E_ROLES);
    $users = User::whereIn('email', $emails)->get();
    $ids = $users->modelKeys();
    $owner = $users->firstWhere('email', $emails[0]);

    $result = match ($input['operation']) {
        'setup' => DB::transaction(function () use ($input, $emails): array {
            $before = privacyE2eForeignState();
            $accounts = [];
            foreach (PRIVACY_E2E_ROLES as $index => $role) {
                $user = User::factory()->create([
                    'email' => $emails[$index],
                    'username' => 'prive2e_'.substr($input['run'], 0, 12).'_'.$role,
                    'display_name' => 'PrivE2E '.ucfirst($role).' '.substr($input['run'], 0, 12),
                    'password' => $input['password'],
                    'email_verified_at' => now(),
                    'trial_ends_at' => now()->addDays(30),
                ]);
                $accounts[$role] = ['id' => $user->id, 'email' => $user->email, 'username' => $user->username,
                    'display_name' => $user->display_name];
            }

            return ['accounts' => $accounts, 'foreign' => $before,
                'leftovers' => User::where('email', 'like', 'privacy-e2e-%')->whereNotIn('email', $emails)->count()];
        }),
        // The stored row of the owner exactly as the database holds it (null = never saved, defaults apply).
        'privacy' => ['row' => $owner === null ? null : DB::table('privacy_settings')->where('user_id', $owner->id)
            ->first(['profile_audience', 'default_post_visibility', 'follower_count_audience', 'mentions_audience',
                'replies_audience', 'carry_audience', 'chat_invites_audience', 'searchable', 'search_engines'])],
        'state' => [
            'notifications' => DB::table('notifications')->where('notifiable_id', $owner?->id)->get()
                ->map(fn ($row) => json_decode($row->data, true) + ['type' => $row->type])->values()->all(),
            'posts' => Post::whereIn('user_id', $ids)->orderBy('id')
                ->get(['id', 'user_id', 'reply_to_id', 'carried_post_id', 'visibility', 'body'])->toArray(),
            'follows' => Follow::whereIn('follower_id', $ids)->get(['follower_id', 'followable_id', 'followable_type'])->toArray(),
            'blocks' => Block::whereIn('user_id', $ids)->get(['user_id', 'blocked_user_id'])->toArray(),
            'conversations' => Conversation::whereIn('created_by', $ids)->count(),
        ],
        'cleanup' => (function () use ($users, $ids, $emails): array {
            $postIds = Post::whereIn('user_id', $ids)->pluck('id')->all();
            $conversations = Conversation::whereHas('memberships', fn ($q) => $q->whereIn('user_id', $ids))
                ->orWhereIn('created_by', $ids)->pluck('id')->all();
            // Posts are removed by foreign keys, so their polymorphic content checks and pending queue jobs are not.
            $checks = ContentCheck::where('checkable_type', (new Post)->getMorphClass())->whereIn('checkable_id', $postIds)->pluck('id')->all();
            $jobs = DB::table('jobs')->get(['id', 'payload'])->filter(function ($job) use ($checks): bool {
                return preg_match('/ContentCheck\\\\";s:2:\\\\"id\\\\";i:(\d+);/', $job->payload, $m) === 1
                    && in_array((int) $m[1], $checks, true);
            })->pluck('id')->all();
            DB::table('jobs')->whereIn('id', $jobs)->delete();
            ContentCheck::whereIn('id', $checks)->delete();
            foreach ($users as $user) {
                app(DeleteAccount::class)->handle($user);
            }
            Conversation::whereIn('id', $conversations)->whereDoesntHave('memberships')->delete();

            return ['deleted_users' => count($ids), 'deleted_jobs' => count($jobs), 'deleted_checks' => count($checks),
                'foreign' => privacyE2eForeignState(), 'remaining' => [
                    'users' => User::whereIn('email', $emails)->count(),
                    'any_privacy_e2e_users' => User::where('email', 'like', 'privacy-e2e-%')->count(),
                    'privacy_settings' => PrivacySetting::whereIn('user_id', $ids)->count(),
                    'posts' => Post::whereIn('id', $postIds)->count(),
                    'notifications' => DB::table('notifications')->whereIn('notifiable_id', $ids)->count(),
                    'notifications_about_test_posts' => DB::table('notifications')->where(function ($q) use ($postIds, $ids) {
                        foreach ($postIds as $id) {
                            $q->orWhere('data', 'like', '%"post_id":'.$id.',%');
                        }
                        foreach ($ids as $id) {
                            $q->orWhere('data', 'like', '%"actor_id":'.$id.',%');
                        }
                    })->when($postIds === [] && $ids === [], fn ($q) => $q->whereRaw('0 = 1'))->count(),
                    'follows' => Follow::whereIn('follower_id', $ids)->orWhere(fn ($q) => $q
                        ->where('followable_type', (new User)->getMorphClass())->whereIn('followable_id', $ids))->count(),
                    'blocks' => Block::whereIn('user_id', $ids)->orWhereIn('blocked_user_id', $ids)->count(),
                    'identities' => ChatIdentity::whereIn('user_id', $ids)->count(),
                    'conversations' => Conversation::whereIn('id', $conversations)->count(),
                    'memberships' => ConversationMember::whereIn('user_id', $ids)->count(),
                    'content_checks' => ContentCheck::whereIn('id', $checks)->count(),
                    'sessions' => DB::table('sessions')->whereIn('user_id', $ids)->count(),
                ]];
        })(),
        default => throw new InvalidArgumentException('Unknown fixture operation.'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Request/cookie bodies and connection credentials must never enter test output.
    fwrite(STDERR, 'Privacy E2E fixture failed: '.$exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
