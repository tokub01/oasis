<?php

namespace App\Models;

use App\Models\Concerns\PrunedAfterRecordRetention;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
#[Hidden(['name', 'email'])]
#[Fillable(['url', 'reason', 'name', 'email', 'truthful_confirmed_at'])]
class ContentReport extends Model
{
    use PrunedAfterRecordRetention { prunable as retentionQuery; }

    /**
     * Keep report evidence while a moderation case still needs it; cases prune first.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return $this->retentionQuery()->whereDoesntHave('moderationCase');
    }

    /**
     * Link the public notice to its staff case through the stable source morph.
     *
     * @return MorphOne<ModerationCase, $this>
     */
    public function moderationCase(): MorphOne
    {
        return $this->morphOne(ModerationCase::class, 'source');
    }

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
