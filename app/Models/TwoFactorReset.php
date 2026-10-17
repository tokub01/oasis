<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to switch off a lost second factor (ManageTwoFactorReset). States, in order:
 * requested (link sent, `link_expires_at`) → confirmed (link opened, waiting until `available_at`) → completed;
 * cancelled or expired at any point. Only the SHA-256 of the link token is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property CarbonInterface $link_expires_at
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $available_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 * @property User $user
 */
class TwoFactorReset extends Model
{
    use MassPrunable;

    /** Hours between confirming the e-mail and the reset becoming possible. */
    public const WAIT_HOURS = 72;

    /** Hours the e-mail link stays valid before it is confirmed. */
    public const LINK_HOURS = 24;

    /** Days after the waiting period within which the reset must be finished. */
    public const COMPLETE_DAYS = 7;

    protected $fillable = ['token_hash', 'link_expires_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['link_expires_at' => 'datetime', 'confirmed_at' => 'datetime', 'available_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** requested|waiting|available|completed|cancelled|expired */
    public function state(): string
    {
        return match (true) {
            $this->completed_at !== null => 'completed',
            $this->cancelled_at !== null => 'cancelled',
            $this->confirmed_at === null => $this->link_expires_at->isPast() ? 'expired' : 'requested',
            $this->available_at->isFuture() => 'waiting',
            $this->available_at->copy()->addDays(self::COMPLETE_DAYS)->isPast() => 'expired',
            default => 'available',
        };
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('updated_at', '<', now()->subDays(30));
    }
}
