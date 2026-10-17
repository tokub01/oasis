<?php

namespace App\Actions\Network;

use App\Models\Post;
use App\Models\PostImage;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Stores one uploaded image of a Tropfen on the private media disk (`oasis.media_disk`: local or the S3 bucket),
 * re-encoded as WebP without metadata (App\Support\Media\WebpImage). Large images are scaled down to MAX_EDGE pixels.
 */
class StorePostImage
{
    /** Longest edge in pixels after scaling. */
    public const MAX_EDGE = 2048;

    /** @throws ValidationException if the file cannot be decoded as an image */
    public function handle(Post $post, UploadedFile $file, string $alt, int $position): PostImage
    {
        try {
            $image = WebpImage::encode($file, self::MAX_EDGE);
        } catch (InvalidArgumentException $failure) {
            throw ValidationException::withMessages(["images.$position" => $failure->getMessage()]);
        }

        $path = 'network/posts/'.$post->id.'/'.Str::random(32).'.webp';
        Storage::disk(config('oasis.media_disk'))->put($path, $image['bytes']);

        return $post->images()->create([
            'path' => $path, 'alt' => $alt, 'width' => $image['width'], 'height' => $image['height'], 'position' => $position,
        ]);
    }
}
