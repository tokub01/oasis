<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A retained withdrawal declaration received by WithdrawalController under §§ 355 ff. BGB.
 * Used by the personal receipt and operator copy; processing or refunds happen separately.
 *
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
     * Expose stored dates and counts with the types used by controllers and receipt views.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contracted_on' => 'date',
        ];
    }
}
