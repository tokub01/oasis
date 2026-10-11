<?php

namespace App\Models;

use App\Models\Concerns\PrunedAfterRecordRetention;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A participant-forwarded chat message report for moderation review.
 * The plaintext is supplied by the reporting participant and cannot be verified by the server.
 * Used by chat reporting actions, moderation case views, and retention pruning.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $reporter_id
 * @property int|null $sender_id
 * @property array<string, mixed> $envelope
 * @property string $plaintext
 * @property string $reason
 * @property string $verification_note
 * @property CarbonImmutable|null $message_expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Hidden(['reporter_id'])]
#[Fillable(['conversation_id', 'reporter_id', 'sender_id', 'envelope', 'plaintext', 'reason', 'verification_note', 'message_expires_at'])]
class ChatReport extends Model
{
    use PrunedAfterRecordRetention { prunable as retentionQuery; }

    /**
     * Keep report evidence while a moderation case still needs it; cases prune first.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return $this->retentionQuery()->whereDoesntHave('moderationCase');
    }

    /**
     * Cast JSON envelope and immutable dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'envelope' => 'array',
            'message_expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Link the participant report to its staff case through the stable source morph.
     *
     * @return MorphOne<ModerationCase, $this>
     */
    public function moderationCase(): MorphOne
    {
        return $this->morphOne(ModerationCase::class, 'source');
    }

    /**
     * The conversation from which the message was reported.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The member who reported the message (hidden from staff views and serializations).
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The sender whose message was reported (subject of the moderation case).
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Public API serialization contract.
     *
     * @return array<string, mixed>
     */
    public function apiData(): array
    {
        return [
            'id' => (string) $this->id,
            'conversation_id' => (string) $this->conversation_id,
            'created_at' => $this->created_at?->toISOString(),
            'status' => 'received',
        ];
    }
}
