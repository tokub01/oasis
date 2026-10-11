<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
