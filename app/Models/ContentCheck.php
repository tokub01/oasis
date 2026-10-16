<?php

namespace App\Models;

use App\Models\Concerns\PrunedAfterRecordRetention;
use App\Support\Moderation\ContentVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Result of the automated check of one text (a Post, a Profile or an Oasis). Stores only scores and the model's short
 * reason – the text stays in its own table. A flagged check is the source of a moderation case (morph alias
 * `content_check`). Kept like other moderation records (`oasis.privacy.records_years`).
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
    use PrunedAfterRecordRetention;

    protected $fillable = ['checkable_type', 'checkable_id', 'status', 'scores', 'reason', 'source', 'flagged', 'attempts', 'checked_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scores' => 'array', 'flagged' => 'boolean', 'checked_at' => 'datetime'];
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
