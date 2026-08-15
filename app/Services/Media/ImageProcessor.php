<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $driver = config('media.driver', 'gd') === 'imagick' && extension_loaded('imagick')
            ? new ImagickDriver
            : new GdDriver;

        $this->manager = new ImageManager($driver);
    }

    /**
     * @return array{
     *     main: array{binary: string, mime: string, extension: string, width: int, height: int, size: int},
     *     variants: array<string, array{binary: string, mime: string, extension: string, width: int, height: int, size: int}>
     * }
     */
    public function processRaster(string $absolutePath): array
    {
        $image = $this->manager->decodePath($absolutePath);
        $maxWidth = (int) config('media.max_width', 2560);
        $quality = (int) config('media.webp_quality', 82);

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $mainEncoded = $image->encodeUsingFormat(Format::WEBP, quality: $quality);
        $mainBinary = (string) $mainEncoded;
        $main = [
            'binary' => $mainBinary,
            'mime' => 'image/webp',
            'extension' => 'webp',
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen($mainBinary),
        ];

        $variants = [];
        foreach (config('media.sizes', []) as $name => $width) {
            $variantImage = $this->manager->decodePath($absolutePath);
            if ($variantImage->width() > $maxWidth) {
                $variantImage->scaleDown(width: $maxWidth);
            }
            $variantImage->scaleDown(width: (int) $width);
            $encoded = $variantImage->encodeUsingFormat(Format::WEBP, quality: $quality);
            $binary = (string) $encoded;
            $variants[$name] = [
                'binary' => $binary,
                'mime' => 'image/webp',
                'extension' => 'webp',
                'width' => $variantImage->width(),
                'height' => $variantImage->height(),
                'size' => strlen($binary),
            ];
        }

        return [
            'main' => $main,
            'variants' => $variants,
        ];
    }

    /**
     * Generate a simple WebP thumb for GIF (first frame / raster decode).
     *
     * @return array{binary: string, mime: string, extension: string, width: int, height: int, size: int}|null
     */
    public function makeThumbOnly(string $absolutePath): ?array
    {
        try {
            $image = $this->manager->decodePath($absolutePath);
            $thumbWidth = (int) (config('media.sizes.thumb') ?? 150);
            $quality = (int) config('media.webp_quality', 82);
            $image->scaleDown(width: $thumbWidth);
            $encoded = $image->encodeUsingFormat(Format::WEBP, quality: $quality);
            $binary = (string) $encoded;

            return [
                'binary' => $binary,
                'mime' => 'image/webp',
                'extension' => 'webp',
                'width' => $image->width(),
                'height' => $image->height(),
                'size' => strlen($binary),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    public function dimensions(string $absolutePath): array
    {
        try {
            $image = $this->manager->decodePath($absolutePath);

            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Throwable $e) {
            throw new RuntimeException('تعذر قراءة أبعاد الصورة: '.$e->getMessage(), 0, $e);
        }
    }
}
