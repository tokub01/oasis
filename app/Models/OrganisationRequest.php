<?php

namespace App\Models;

use App\Models\Concerns\PrunedAfterRecordRetention;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An organisation enquiry received by OrganisationController.
 * Stores the approximate group size and reply address for operator follow-up.
 *
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
    use PrunedAfterRecordRetention;

    /**
     * Expose stored dates and counts with the types used by controllers and receipt views.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'people' => 'integer',
        ];
    }
}
