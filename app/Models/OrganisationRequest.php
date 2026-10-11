<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organisation
 * @property int $people
 * @property string $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organisation', 'people', 'email'])]
class OrganisationRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'people' => 'integer',
        ];
    }
}
