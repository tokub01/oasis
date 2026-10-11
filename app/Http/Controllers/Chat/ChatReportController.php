<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\ReportMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ReportMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Handles participant-initiated moderation reports of decrypted messages.
 * Staff review only the forwarded message plaintext and metadata, never wider conversation history.
 * Bound to POST /app/chat/api/conversations/{conversation}/reports.
 */
class ChatReportController extends Controller
{
    /** Store the reported message and open a moderation case under transaction. */
    public function store(ReportMessageRequest $request, Conversation $conversation, ReportMessage $report): JsonResponse
    {
        $chatReport = $report->handle(
            $conversation,
            $request->user(),
            $request->validated('envelope'),
            $request->validated('plaintext'),
            $request->validated('reason')
        );

        return response()->json(['data' => $chatReport->apiData()], 201);
    }
}
