<?php

namespace App\Actions\Network;

use App\Models\ContentCheck;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a Tropfen with its image files. Replies and carries of it keep existing and show
 * "Tropfen nicht mehr verfügbar" (they keep the id of the deleted Tropfen). Its automated check goes too, unless a
 * moderation case is based on it (Art. 17 GDPR, see ContentCheck).
 */
class DeletePost
{
    public function handle(Post $post): void
    {
        Storage::disk(config('oasis.media_disk'))->deleteDirectory('network/posts/'.$post->id);
        $post->delete();
        ContentCheck::orphaned()->whereMorphedTo('checkable', $post)->delete();
    }
}
