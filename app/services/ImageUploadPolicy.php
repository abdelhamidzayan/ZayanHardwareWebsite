<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Shared server-side image header validation.
 *
 * getimagesize() reads image metadata without decoding the full bitmap. The
 * pixel ceiling is deliberately separate from the upload byte-size ceiling:
 * highly compressed images can otherwise request an excessive decoded bitmap.
 */
final class ImageUploadPolicy
{
    public const MAX_DIMENSION = 8000;
    public const MAX_PIXELS = 20_000_000;

    /**
     * @return array{width:int,height:int,pixels:int}
     */
    public static function inspect(
        string $path,
        int $maxDimension = self::MAX_DIMENSION,
        int $maxPixels = self::MAX_PIXELS
    ): array {
        if ($path === '' || !is_file($path) || !is_readable($path)
            || $maxDimension < 1 || $maxPixels < 1) {
            throw new \DomainException('image_invalid');
        }

        $image = @getimagesize($path);
        if (!is_array($image) || !isset($image[0], $image[1])) {
            throw new \DomainException('image_invalid');
        }

        $width = (int) $image[0];
        $height = (int) $image[1];
        if ($width < 1 || $height < 1
            || $width > $maxDimension
            || $height > $maxDimension
            || $width > intdiv($maxPixels, $height)) {
            throw new \DomainException('image_dimensions_invalid');
        }

        return [
            'width' => $width,
            'height' => $height,
            'pixels' => $width * $height,
        ];
    }
}
