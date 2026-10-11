<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'truthful_confirmed_at' => 'datetime',
        ];
    }
}
