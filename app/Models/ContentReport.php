<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public content notice received by ContentReportController under DSA Art. 16.
 * truthful_confirmed_at records the good-faith declaration, not a moderation decision.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $url
 * @property string $reason
 * @property Carbon $truthful_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'reason', 'name', 'email', 'truthful_confirmed_at'])]
class ContentReport extends Model
{
    /**
     * Expose stored dates and counts with the types used by controllers and receipt views.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'truthful_confirmed_at' => 'datetime',
        ];
    }
}
