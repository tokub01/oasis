<?php

namespace App\Models;

use App\Http\Controllers\Network\PostImageController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image of a Tropfen. Files live on the private media disk (`oasis.media_disk`) and are served through
 * {@see PostImageController} with the Tropfen's visibility check; they are re-encoded
 * on upload so camera metadata (e.g. GPS location) never gets published.
 *
 * @property int $id
 * @property int $post_id
 * @property string $path
 * @property string $alt
 * @property int $width
 * @property int $height
 * @property int $position
 * @property Post $post
 */
class PostImage extends Model
{
    public $timestamps = false;

    protected $fillable = ['path', 'alt', 'width', 'height', 'position'];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
