<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Minimal public-key directory for picking chat-ready usernames and verifying conversation members. */
class PublicKeysController extends Controller
{
    /** Exact usernames only; do not expose an account-wide directory or email addresses. */
    public function users(Request $request): JsonResponse
    {
        $input = $request->validate(['username' => ['required', 'string', 'regex:/^[a-z0-9_]{3,30}$/D']]);
        $users = User::query()->where('username', $input['username'])->whereNotNull('email_verified_at')
            ->whereIn('id', ChatIdentity::query()->select('user_id'))
            ->whereNotIn('id', $request->user()->blockedUserIds(true))->get();

        return response()->json(['data' => $this->data($users)]);
    }

    /** Past members' public bundles remain available to verify their unexpired historical messages. */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->requireMember($request->user());
        $users = User::query()->whereIn('id', $conversation->memberships()->select('user_id'))->get();

        return response()->json(['data' => $this->data($users)]);
    }

    /**
     * Public profile allowlist excludes encrypted private blobs, email, birth date and account roles.
     *
     * @param  Collection<int, User>  $users
     * @return list<array<string, mixed>>
     */
    private function data(Collection $users): array
    {
        $identities = ChatIdentity::whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');

        return array_values($users->map(fn (User $user) => ['id' => (string) $user->id, 'username' => $user->username,
            'display_name' => $user->display_name, 'public_keys' => $identities[$user->id]->public_keys ?? null,
            'key_changed_at' => ($identities[$user->id] ?? null)?->key_changed_at?->toISOString()])->all());
    }
}
