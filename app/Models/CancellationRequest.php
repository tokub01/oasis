<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $username
 * @property string $contract
 * @property string $type
 * @property string|null $reason
 * @property string $timing
 * @property Carbon|null $effective_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'username', 'contract', 'type', 'reason', 'timing', 'effective_date'])]
class CancellationRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }
}
