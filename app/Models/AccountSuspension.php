<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lock of a whole account (docs/oasis-moderation.md, "Sperren"). While active (ended_at null) the member only sees
 * /app/gesperrt with the accusation and a statement field, plus settings, export, membership and logout.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $moderation_case_id
 * @property int|null $post_id
 * @property string $type provisional|suspension|ban
 * @property string $accusation
 * @property string|null $excerpt
 * @property string|null $statement
 * @property Carbon|null $statement_at
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $ended_by
 * @property string|null $result released|confirmed|overturned
 */
class AccountSuspension extends Model
{
    public const PROVISIONAL = 'provisional';

    public const SUSPENSION = 'suspension';

    public const BAN = 'ban';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['statement_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ModerationCase, $this> */
    public function moderationCase(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @param  Builder<AccountSuspension>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /** German label for staff and the member. */
    public function label(): string
    {
        return match ($this->type) {
            self::PROVISIONAL => 'Vorläufige Sperre bis zur Prüfung',
            self::BAN => 'Dauerhafte Sperre',
            default => 'Sperre durch das Moderationsteam',
        };
    }
}
