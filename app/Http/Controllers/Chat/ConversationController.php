<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\ManageConversation;
use App\Actions\Privacy\UpdatePrivacySetting;
use App\Actions\Safety\ManageBlock;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use App\Support\Chat\UnreadChats;
use App\Support\Privacy\PrivacyOptions;
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
        $pending = ConversationMember::where('user_id', $request->user()->id)->whereNull('accepted_at')->pluck('conversation_id')->all();
        $page->through(fn ($conversation) => $conversation->apiData() + ['unread' => in_array($conversation->id, $unread, true),
            'request' => in_array($conversation->id, $pending)]);

        return response()->json($page);
    }

    /** Chats with unread messages, for the badge at "Chats" (metadata only, docs/oasis-chat.md). */
    public function unread(Request $request): JsonResponse
    {
        $ids = UnreadChats::conversationIds($request->user());

        return response()->json(['count' => count($ids), 'conversation_ids' => array_map('strval', $ids),
            'requests' => UnreadChats::requestCount($request->user())]);
    }

    /** The member has the chat open: everything until now counts as seen, and a later message may notify again. */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->requireMember($request->user())->forceFill(['last_read_at' => now()])->save();

        return response()->json(['count' => UnreadChats::count($request->user())]);
    }

    /** Accept a message request. */
    public function accept(Request $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->accept($conversation, $request->user());

        return response()->json(['accepted' => true]);
    }

    /** Who may write to the member and whether friend requests reach them – changeable right in the chat. */
    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->chatSettings($request->user()));
    }

    public function updateSettings(Request $request, UpdatePrivacySetting $update): JsonResponse
    {
        $input = $request->validate(['key' => ['required', 'in:chat_invites_audience,friend_requests'], 'value' => ['required']]);
        $update->handle($request->user(), $input['key'], $input['key'] === 'friend_requests' ? filter_var($input['value'], FILTER_VALIDATE_BOOLEAN) : $input['value']);

        return response()->json($this->chatSettings($request->user()->fresh()));
    }

    /** @return array<string, mixed> */
    private function chatSettings(User $member): array
    {
        return ['chat_invites_audience' => $member->privacy()->chat_invites_audience->value,
            'friend_requests' => $member->privacy()->friend_requests,
            'choices' => PrivacyOptions::choices('chat_invites_audience', $member)];
    }

    /** Leave the chat (it disappears from the list); see ManageConversation::leave. */
    public function leave(Request $request, Conversation $conversation, ManageConversation $manage): JsonResponse
    {
        $manage->leave($conversation, $request->user());

        return response()->json(['left' => true]);
    }

    /** Block a person right from the chat (safety banner); always allowed, also for read-only accounts. */
    public function block(Request $request, ManageBlock $blocks): JsonResponse
    {
        $input = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $blocks->block($request->user(), User::whereKey((int) $input['user_id'])->firstOrFail());

        return response()->json(['blocked' => true]);
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
        $membership = $conversation->requireMember($request->user());

        return response()->json(['data' => $conversation->apiData() + ['request' => $membership->isRequest()]]);
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
