<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SaveIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\IdentityRequest;
use App\Models\ChatIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Own-identity retrieval/replacement used by the in-memory chat unlock flow. */
class IdentityController extends Controller
{
    /** Return only the caller's locked blob, never another account's encrypted private identity. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->data(ChatIdentity::where('user_id', $request->user()->id)->first())]);
    }

    /** Validate cryptographic public data before delegating atomic identity replacement. */
    public function update(IdentityRequest $request, SaveIdentity $save): JsonResponse
    {
        /** @var array{locked_identity: array<string, mixed>, public_keys: array<string, string>} $input */
        $input = $request->validated();

        return response()->json(['data' => $this->data($save->handle($request->user(), $input))]);
    }

    /**
     * Explicit response allowlist avoids accidental model relationship leakage.
     *
     * @return array<string, mixed>|null
     */
    private function data(?ChatIdentity $identity): ?array
    {
        return $identity ? ['user_id' => (string) $identity->user_id, 'locked_identity' => $identity->locked_identity,
            'public_keys' => $identity->public_keys, 'key_changed_at' => $identity->key_changed_at?->toISOString()] : null;
    }
}
