<?php

namespace App\Models;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\Priority;
use App\Models\Concerns\PrunedAfterRecordRetention;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic moderation work item, its assignment and retained workflow history.
 * Used by moderation actions and authorized staff views.
 *
 * @property CaseStatus $status
 * @property Priority $priority
 * @property Model|null $source
 * @property User|null $subjectUser
 * @property User|null $assignee
 * @property ModerationDecision|null $latestDecision
 */
#[Fillable(['source_type', 'source_id', 'subject_url', 'subject_user_id', 'status', 'assigned_to', 'priority'])]
class ModerationCase extends Model
{
    use PrunedAfterRecordRetention { prunable as retentionQuery; }

    /**
     * Keep unresolved cases and the six-month complaint window, even with shorter configured retention.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        /** @var Builder<static> $query */
        $query = $this->retentionQuery();

        return $query->whereIn('status', ['decided', 'closed'])
            ->where('updated_at', '<', now()->subYears((int) config('oasis.privacy.records_years')))
            ->whereDoesntHave('latestDecision', fn (Builder $query) => $query
                ->whereNull('notified_at')->orWhere('notified_at', '>=', now()->subMonthsNoOverflow(6)));
    }

    /**
     * Cast workflow values and dates for the UI and domain actions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'priority' => Priority::class,
        ];
    }

    /**
     * Load the source relationship for case processing and staff views.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Load the subjectUser relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * Load the assignee relationship for case processing and staff views.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Load the decisions relationship for case processing and staff views.
     *
     * @return HasMany<ModerationDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class, 'case_id');
    }

    /**
     * Load the latestDecision relationship for case processing and staff views.
     *
     * @return HasOne<ModerationDecision, $this>
     */
    public function latestDecision(): HasOne
    {
        return $this->hasOne(ModerationDecision::class, 'case_id')->latestOfMany();
    }

    /**
     * Load the complaints relationship for case processing and staff views.
     *
     * @return HasMany<ModerationComplaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(ModerationComplaint::class, 'case_id');
    }

    /**
     * Load the events relationship for case processing and staff views.
     *
     * @return HasMany<ModerationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ModerationEvent::class, 'case_id')->orderBy('id');
    }
}
