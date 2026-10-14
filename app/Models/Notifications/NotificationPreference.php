<?php

namespace App\Models\Notifications;

use App\Enums\Notifications\NoticeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-account hint preferences and the durable daily digest checkpoint; never extends users.
 *
 * @property int $user_id
 * @property array<string, bool>|null $kinds
 * @property bool $digest_enabled
 * @property Carbon|null $last_digest_at
 */
class NotificationPreference extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kinds' => 'array', 'digest_enabled' => 'boolean', 'last_digest_at' => 'datetime'];
    }

    /** Hint kinds default on; daily email defaults off at the database level. */
    public function enabled(NoticeKind $kind): bool
    {
        return ($this->kinds[$kind->value] ?? true) === true;
    }
}
