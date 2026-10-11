<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\ConversationKeys;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\GrantRequest;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Recipient-only grant retrieval plus owner-only authenticated distribution/activation. */
class KeyGrantController extends Controller
{
    /** An active member retrieves only their own grants, including retained historical versions. */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->requireMember($request->user());
        $grants = ConversationKeyGrant::where('conversation_id', $conversation->id)
            ->where('recipient_id', $request->user()->id)->orderBy('key_version')->pluck('grant');

        return response()->json(['data' => $grants]);
    }

    /** Complete grant batches are signed for the current recipients and current pending key. */
    public function store(GrantRequest $request, Conversation $conversation, ConversationKeys $keys): JsonResponse
    {
        return response()->json(['data' => $keys->distribute($conversation, $request->user(), $request->validated())->apiData()]);
    }

    /** Mark a fully distributed version ready; optimistic version check detects intervening changes. */
    public function rotate(Request $request, Conversation $conversation, ConversationKeys $keys): JsonResponse
    {
        $input = $request->validate(['key_version' => ['required', 'integer', 'min:1', 'max:4294967295']]);

        return response()->json(['data' => $keys->activate($conversation, $request->user(), (int) $input['key_version'])->apiData()]);
    }
}
