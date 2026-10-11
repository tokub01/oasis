<?php

namespace App\Models;

use App\Enums\Moderation\EventType;
use App\Models\Builders\ModerationEventBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only moderation audit evidence; only case retention may cascade its removal.
 * Used by moderation actions and authorized staff views.
 *
 * @property EventType $type
 * @property array<string, mixed> $data
 * @property Carbon $created_at
 * @property ModerationCase $case
 * @property User|null $actor
 */
#[UseEloquentBuilder(ModerationEventBuilder::class)]
#[Fillable(['case_id', 'actor_id', 'type', 'data', 'created_at'])]
class ModerationEvent extends Model
{
    public $timestamps = false;

    /** Audit history is append-only through Eloquent; retention deletes the parent and cascades history. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.'));
        static::deleting(fn () => throw new LogicException('Moderationsereignisse dürfen nicht gelöscht werden.'));
    }

    /**
     * Cast workflow values and dates for the UI and domain actions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Load the case relationship for case processing and staff views.
     *
     * @return BelongsTo<ModerationCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class, 'case_id');
    }

    /**
     * Load the actor relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
