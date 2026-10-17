<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a Tropfen image from the private media disk (local or S3, never a public bucket URL) after the same
 * visibility check as the Tropfen. Public images may be cached by browsers; members-only images are marked private
 * so shared caches never keep them. A quarantined Tropfen (escalated as child abuse) serves its images to nobody, staff
 * included; admins reach the secured copies only through the evidence download.
 */
class PostImageController extends Controller
{
    public function __invoke(Request $request, Post $post, int $image): StreamedResponse
    {
        abort_unless($post->quarantined_at === null && Gate::forUser($request->user())->allows('view', $post), 404);
        $file = $post->images()->whereKey($image)->firstOrFail();
        $disk = Storage::disk(config('oasis.media_disk'));
        // A record whose file is gone (e.g. lost in storage) is a missing image, not a server error.
        abort_unless($disk->exists($file->path), 404);

        return $disk->response($file->path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => $post->visibility->value === 'public' ? 'public, max-age=86400' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
