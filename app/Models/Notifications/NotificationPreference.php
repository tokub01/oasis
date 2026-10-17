<?php

namespace App\Models\Notifications;

use App\Enums\Notifications\NoticeKind;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-account hint preferences, quiet hours, pause and the durable daily digest checkpoint; never extends users.
 *
 * Quiet hours (`quiet_from`–`quiet_until`, German time, may span midnight) and a pause (`paused_until`) stop hint mails
 * and pushes; hints still collect in the app. Account, security and moderation mails are not affected.
 *
 * @property int $user_id
 * @property array<string, bool>|null $kinds
 * @property bool $digest_enabled
 * @property Carbon|null $last_digest_at
 * @property bool $quiet_enabled
 * @property string $quiet_from H:i:s
 * @property string $quiet_until H:i:s
 * @property Carbon|null $paused_until
 */
class NotificationPreference extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kinds' => 'array', 'digest_enabled' => 'boolean', 'last_digest_at' => 'datetime', 'quiet_enabled' => 'boolean',
            'paused_until' => 'datetime'];
    }

    /** Hint kinds default on; daily email defaults off at the database level. */
    public function enabled(NoticeKind $kind): bool
    {
        return ($this->kinds[$kind->value] ?? true) === true;
    }

    /** Time zone of quiet hours: Oasis is a German-speaking network. */
    public const TIMEZONE = 'Europe/Berlin';

    public function isPaused(?CarbonInterface $at = null): bool
    {
        return $this->paused_until !== null && $this->paused_until->greaterThan($at ?? now());
    }

    /** Inside the daily quiet window (German time), which may span midnight (e.g. 22:00–07:00). */
    public function inQuietHours(?CarbonInterface $at = null): bool
    {
        if (! $this->quiet_enabled) {
            return false;
        }
        $time = ($at ?? now())->copy()->timezone(self::TIMEZONE)->format('H:i');
        $from = substr($this->quiet_from, 0, 5);
        $until = substr($this->quiet_until, 0, 5);

        return $from <= $until ? ($time >= $from && $time < $until) : ($time >= $from || $time < $until);
    }

    /** No hint mails or pushes now: paused or inside the quiet hours. */
    public function isQuiet(?CarbonInterface $at = null): bool
    {
        return $this->isPaused($at) || $this->inQuietHours($at);
    }

    /** For callers that only have the account (no row = never quiet). */
    public static function quietFor(User $user, ?CarbonInterface $at = null): bool
    {
        return static::where('user_id', $user->id)->first()?->isQuiet($at) ?? false;
    }
}
