<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A founding waitlist entry managed by WaitlistController.
 * Stores double-opt-in consent separately from signed confirmation; token identifies both signed link flows.
 *
 * @property int $id
 * @property string $email
 * @property string|null $group
 * @property Carbon $consented_at
 * @property string $token
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'group', 'consented_at', 'token', 'confirmed_at'])]
class WaitlistEntry extends Model
{
    use MassPrunable;

    /**
     * Unconfirmed sign-ups older than `oasis.privacy.unconfirmed_waitlist_days` are deleted by the daily
     * `model:prune` run (double opt-in never completed, so there is no consent to keep the address).
     * Nothing is deleted while that period is not set (null).
     */
    public function prunable(): Builder
    {
        $days = config('oasis.privacy.unconfirmed_waitlist_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays((int) $days));
    }

    /**
     * Expose stored dates and counts with the types used by controllers and receipt views.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
