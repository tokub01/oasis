<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\ManageConversation;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Support\Chat\UnreadChats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Conversation metadata API; membership mutations are delegated to transaction-owning actions. */
class ConversationController extends Controller
{
    /** Paginate only active memberships, never conversations the caller has left. */
    public function index(Request $request): JsonResponse
    {
        $page = Conversation::query()->whereHas('activeMembers',
            fn ($query) => $query->where('user_id', $request->user()->id))->orderByDesc('id')->paginate(30);
        $unread = UnreadChats::conversationIds($request->user());
        $page->through(fn ($conversation) => $conversation->apiData() + ['unread' => in_array($conversation->id, $unread, true)]);

        return response()->json($page);
    }

    /** Chats with unread messages, for the badge at "Chats" (metadata only, docs/oasis-chat.md). */
    public function unread(Request $request): JsonResponse
    {
        $ids = UnreadChats::conversationIds($request->user());

        return response()->json(['count' => count($ids), 'conversation_ids' => array_map('strval', $ids)]);
    }

    /** The member has the chat open: everything until now counts as seen, and a later message may notify again. */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->requireMember($request->user())->forceFill(['last_read_at' => now()])->save();

        return response()->json(['count' => UnreadChats::count($request->user())]);
    }

    /** Create a pending-key chat with distinct, explicitly selected account IDs. */
    public function store(Request $request, ManageConversation $manage): JsonResponse
    {
        $input = $request->validate([
            'type' => ['required', 'in:direct,group'], 'title' => ['nullable', 'string', 'max:100'],
            'member_ids' => ['required', 'array', 'list', 'min:1', 'max:49'],
            'member_ids.*' => ['required', 'integer', 'min:1', 'max:9007199254740991', 'distinct'],
            'lifetime_days' => ['required', 'integer', 'in:1,7,30'],
        ]);

        return response()->json(['data' => $manage->create($request->user(), $input)->apiData()], 201);
    }

    /** Authorize before returning metadata or the server clock for signed expiry calculation. */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->requireMember($request->user());

        return response()->json(['data' => $conversation->apiData()]);
    }

    /** Owner settings affect future envelopes, never signed existing messages. */
    public function update(Request $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $input = $request->validate(['title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lifetime_days' => ['sometimes', 'required', 'integer', 'in:1,7,30']]);

        return response()->json(['data' => $manage->settings($conversation, $request->user(), $input)->apiData()]);
    }

    /** Add a chat-ready group member and require a new key for the new active set. */
    public function add(Request $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $input = $request->validate(['user_id' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);

        return response()->json(['data' => $manage->membership($conversation, $request->user(), (int) $input['user_id'], true)->apiData()]);
    }

    /** Immediately revoke a non-owner's API access and invalidate the shared conversation key. */
    public function remove(Request $request, Conversation $conversation, int $user, ManageConversation $manage): JsonResponse
    {
        return response()->json(['data' => $manage->membership($conversation, $request->user(), $user, false)->apiData()]);
    }
}
