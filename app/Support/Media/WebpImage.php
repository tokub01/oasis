<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Decodes an uploaded image and re-encodes it as WebP with GD (Tropfen images, profile photos). Re-encoding drops
 * every metadata block (EXIF GPS location, camera serial numbers) and anything hidden after the image data. Input
 * bounds apply before GD allocates the decoded bitmap.
 */
final class WebpImage
{
    public const MAX_INPUT_EDGE = 12000;

    public const MAX_INPUT_PIXELS = 40_000_000;

    private const QUALITY = 82;

    /**
     * Scale so the longest edge is at most $maxEdge; with $square, crop the centre to a square first.
     *
     * @return array{bytes: string, width: int, height: int}
     *
     * @throws InvalidArgumentException with a German message when the file is no readable image or too large
     */
    public static function encode(UploadedFile $file, int $maxEdge, bool $square = false): array
    {
        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw new InvalidArgumentException('Dieses Bild können wir nicht lesen.');
        }
        if ($dimensions[0] > self::MAX_INPUT_EDGE || $dimensions[1] > self::MAX_INPUT_EDGE
            || $dimensions[0] * $dimensions[1] > self::MAX_INPUT_PIXELS) {
            throw new InvalidArgumentException('Ein Bild darf höchstens 40 Megapixel und 12000 Pixel pro Kante haben.');
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new InvalidArgumentException('Dieses Bild können wir nicht lesen.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropX = $cropY = 0;
        if ($square) {
            $side = min($sourceWidth, $sourceHeight);
            $cropX = intdiv($sourceWidth - $side, 2);
            $cropY = intdiv($sourceHeight - $side, 2);
            $sourceWidth = $sourceHeight = $side;
        }
        $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, $cropX, $cropY, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagewebp($image, null, self::QUALITY);

        return ['bytes' => (string) ob_get_clean(), 'width' => $width, 'height' => $height];
    }
}
