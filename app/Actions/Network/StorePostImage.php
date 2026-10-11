<?php

namespace App\Actions\Network;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores one uploaded image of a Tropfen on the private media disk (`oasis.media_disk`: local or the S3 bucket).
 *
 * The image is decoded and re-encoded as WebP with GD, which drops every metadata block (EXIF GPS location, camera
 * serial numbers) and any payload hidden after the image data. Large images are scaled down to MAX_EDGE pixels.
 */
class StorePostImage
{
    /** Longest edge in pixels after scaling. */
    public const MAX_EDGE = 2048;

    private const QUALITY = 82;

    /** @throws ValidationException if the file cannot be decoded as an image */
    public function handle(Post $post, UploadedFile $file, string $alt, int $position): PostImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(["images.$position" => 'Dieses Bild können wir nicht lesen.']);
        }

        [$width, $height] = $this->scaledSize(imagesx($source), imagesy($source));
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        $path = 'network/posts/'.$post->id.'/'.Str::random(32).'.webp';
        Storage::disk(config('oasis.media_disk'))->put($path, $bytes);

        return $post->images()->create([
            'path' => $path, 'alt' => $alt, 'width' => $width, 'height' => $height, 'position' => $position,
        ]);
    }

    /** @return array{int, int} */
    private function scaledSize(int $width, int $height): array
    {
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }
}
