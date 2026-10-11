<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\MessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Expiring ciphertext history and authenticated sends; no plaintext ever enters these responses. */
class MessageController extends Controller
{
    /** Cursor pagination never serves expired messages or history before the member's latest join. */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $member = $conversation->requireMember($request->user());
        $input = $request->validate(['before_id' => ['sometimes', 'integer', 'min:1', 'max:9007199254740991']]);
        $messages = Message::where('conversation_id', $conversation->id)->where('expires_at', '>', now())
            ->where('created_at', '>=', $member->joined_at)
            ->when(isset($input['before_id']), fn ($query) => $query->where('id', '<', $input['before_id']))
            ->orderByDesc('id')->limit(51)->get();
        $hasMore = $messages->count() > 50;
        $messages = $messages->take(50);

        return response()->json(['data' => $messages->map(fn ($message) => $message->apiData())->values(),
            'next_before_id' => $hasMore ? (string) $messages->last()->id : null]);
    }

    /** Persist the validated original envelope before dispatching the ciphertext-only event. */
    public function store(MessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        return response()->json(['data' => $send->handle($conversation, $request->user(), $request->validated('envelope'))->apiData()], 201);
    }
}
