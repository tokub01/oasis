<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership history used by chat authorization and current membership rotation.
 *
 * @property CarbonImmutable $joined_at
 * @property CarbonImmutable|null $left_at
 * @property CarbonImmutable|null $accepted_at null while the chat is a message request for this member
 * @property CarbonImmutable|null $last_read_at last time the member had the chat open (UnreadChats)
 * @property CarbonImmutable|null $notified_at last e-mail/push about this chat (NotifyChatRecipients)
 * @property User $user
 */
class ConversationMember extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Membership timestamps establish access boundaries after rejoining.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'last_read_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime'];
    }

    /** Still a message request for this member (docs/oasis-chat.md, "Nachrichtenanfragen"). */
    public function isRequest(): bool
    {
        return $this->accepted_at === null;
    }

    /**
     * Resolve the public account metadata for member responses.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
