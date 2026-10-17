<?php

namespace App\Models;

use App\Models\Concerns\PrunedAfterRecordRetention;
use App\Support\Moderation\ContentVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Result of the automated check of one text (a Post, a Profile or an Oasis). Stores only scores and the model's short
 * reason – the text stays in its own table. A flagged check is the source of a moderation case (morph alias
 * `content_check`). Kept like other moderation records (`oasis.privacy.records_years`).
 *
 * Art. 17 GDPR: once its text is deleted, a check that did not lead to a case is deleted too ("orphaned"). The
 * deleting actions remove it at once; the daily `model:prune` catches texts removed by foreign-key cascades. A check
 * that is the source of a case stays as part of the case documentation until the retention period ends.
 *
 * @property int $id
 * @property string $checkable_type
 * @property int $checkable_id
 * @property string $status pending|checked
 * @property array<string, float>|null $scores
 * @property string|null $reason
 * @property string|null $source ai|wordlist
 * @property bool $flagged
 * @property int $attempts
 * @property Carbon|null $checked_at
 * @property Model|null $checkable
 */
class ContentCheck extends Model
{
    use PrunedAfterRecordRetention {
        prunable as prunableAfterRetention;
    }

    /** Models whose texts are checked. */
    private const CHECKABLES = [Post::class, Profile::class, Oasis::class];

    protected $fillable = ['checkable_type', 'checkable_id', 'status', 'scores', 'reason', 'source', 'flagged', 'attempts', 'checked_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scores' => 'array', 'flagged' => 'boolean', 'checked_at' => 'datetime'];
    }

    /**
     * Records past the retention period plus orphaned checks.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return $this->prunableAfterRetention()->orWhere(fn (Builder $orphaned) => $orphaned->orphaned());
    }

    /**
     * Restrict a query to checks whose text no longer exists and that are not the source of a moderation case.
     *
     * @param  Builder<ContentCheck>  $query
     */
    public function scopeOrphaned(Builder $query): void
    {
        $query->whereNotExists(fn ($case) => $case->from('moderation_cases')
            ->where('source_type', $this->getMorphClass())->whereColumn('source_id', 'content_checks.id'))
            ->where(function (Builder $gone): void {
                foreach (self::CHECKABLES as $class) {
                    $model = new $class;
                    $gone->orWhere(fn (Builder $type) => $type->where('checkable_type', $model->getMorphClass())
                        ->whereNotExists(fn ($row) => $row->from($model->getTable())
                            ->whereColumn($model->getKeyName(), 'content_checks.checkable_id')));
                }
            });
    }

    /** @return MorphTo<Model, $this> */
    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verdict(): ContentVerdict
    {
        return ContentVerdict::fromArray(($this->scores ?? []) + ['reason' => $this->reason], (string) $this->source);
    }

    /** URL of the checked content for the moderation case. */
    public function subjectUrl(): string
    {
        $checkable = $this->checkable;

        return match (true) {
            $checkable instanceof Post => route('network.posts.show', $checkable),
            $checkable instanceof Oasis => route('network.oases.show', $checkable),
            $checkable instanceof Profile => route('oasis.profil', $checkable->user->username),
            default => route('app.home'),
        };
    }

    /** The account responsible for the content (subject of the case). */
    public function subjectUserId(): ?int
    {
        $checkable = $this->checkable;

        return match (true) {
            $checkable instanceof Post => $checkable->user_id,
            $checkable instanceof Profile => $checkable->user_id,
            $checkable instanceof Oasis => $checkable->created_by,
            default => null,
        };
    }
}
