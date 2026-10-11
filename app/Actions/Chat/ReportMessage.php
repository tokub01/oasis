<?php

namespace App\Actions\Chat;

use App\Actions\Moderation\OpenCase;
use App\Models\ChatIdentity;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use App\Support\Chat\CryptoPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validates a participant's forwarded message envelope and registers a moderation report.
 * Plaintext is accepted from the reporting participant without server-side cryptographic verification.
 * Opens a moderation case with source morph 'chat_report' and subject_user_id set to the sender.
 * Used by ChatReportController.
 */
class ReportMessage
{
    /**
     * Verify caller membership, envelope conversation binding, and sender signature before storing.
     *
     * @param  array<string, mixed>  $envelope
     *
     * @throws ValidationException
     */
    public function handle(Conversation $conversation, User $reporter, array $envelope, string $plaintext, string $reason): ChatReport
    {
        return DB::transaction(function () use ($conversation, $reporter, $envelope, $plaintext, $reason) {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $conversation->requireMember($reporter);

            if ($envelope['conversation_id'] !== (string) $conversation->id) {
                throw ValidationException::withMessages([
                    'envelope' => 'Der Umschlag gehört nicht zu dieser Unterhaltung.',
                ]);
            }

            $senderId = (int) $envelope['sender_id'];
            $sender = User::find($senderId);
            $senderIdentity = ChatIdentity::query()->where('user_id', $senderId)->first();

            $isConversationMember = ConversationMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $senderId)
                ->exists();

            if (! $sender || ! $isConversationMember || ! $senderIdentity || ! CryptoPayload::messageValid($envelope, $senderIdentity->public_keys)) {
                throw ValidationException::withMessages([
                    'envelope' => 'Die Nachrichtensignatur ist ungültig.',
                ]);
            }

            $report = ChatReport::create([
                'conversation_id' => $conversation->id,
                'reporter_id' => $reporter->id,
                'sender_id' => $senderId,
                'envelope' => $envelope,
                'plaintext' => $plaintext,
                'reason' => $reason,
                'verification_note' => 'reported by participant, not verifiable by the server',
                'message_expires_at' => CarbonImmutable::parse($envelope['expires_at']),
            ]);

            app(OpenCase::class)->handle($report);

            return $report;
        }, 3);
    }
}
