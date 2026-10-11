<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $contract
 * @property Carbon|null $contracted_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'contract', 'contracted_on'])]
class WithdrawalRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contracted_on' => 'date',
        ];
    }
}
