<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earlier text of an edited Tropfen (EditPost). Readable by everyone who may read the Tropfen, deleted with it.
 *
 * @property int $id
 * @property int $post_id
 * @property string $body
 * @property CarbonInterface $replaced_at
 */
class PostRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['body', 'replaced_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['replaced_at' => 'datetime'];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
