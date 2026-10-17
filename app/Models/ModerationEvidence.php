<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Evidence secured for an escalated case (EscalateCase): a text snapshot, or a copy of an image with its SHA-256
 * checksum. Kept apart from the content, so it survives deletion of the Tropfen or the account (Art. 17 Abs. 3 lit. e
 * DSGVO), visible to staff as metadata only and downloadable by admins. Deleted with its file once `purge_after` has
 * passed (`oasis.moderation.evidence_days`, can be extended).
 *
 * @property int $id
 * @property int $case_id
 * @property string $kind text|image
 * @property string|null $content
 * @property string|null $path path on the media disk, below evidence/
 * @property string|null $sha256
 * @property int|null $bytes
 * @property array<string, mixed> $meta
 * @property Carbon $purge_after
 * @property Carbon $created_at
 */
class ModerationEvidence extends Model
{
    use Prunable;

    protected $table = 'moderation_evidence';

    protected $fillable = ['kind', 'content', 'path', 'sha256', 'bytes', 'meta', 'captured_by', 'purge_after'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array', 'purge_after' => 'datetime'];
    }

    /** @return BelongsTo<ModerationCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class, 'case_id');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('purge_after', '<', now());
    }

    /** The copied file goes with the record. */
    protected function pruning(): void
    {
        $this->deleteFile();
    }

    public function deleteFile(): void
    {
        if ($this->path !== null) {
            Storage::disk(config('oasis.media_disk'))->delete($this->path);
        }
    }
}
