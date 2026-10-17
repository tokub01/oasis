<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser and system a member has signed in with ("Chrome auf Windows"), used for login alerts. Stores only that
 * label and when it was last used – no IP address, no fingerprint. Forgotten after a year without a login.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property Carbon $last_seen_at
 * @property Carbon $created_at
 */
class KnownDevice extends Model
{
    use MassPrunable;

    protected $fillable = ['label', 'last_seen_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<', now()->subYear());
    }
}
