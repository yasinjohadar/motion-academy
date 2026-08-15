<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\Storage\AppStorageManager;

class MediaUrlResolver
{
    public function __construct(
        private readonly AppStorageManager $storage,
    ) {}

    public function url(?string $disk, ?string $path): ?string
    {
        if (! $disk || ! $path) {
            return null;
        }

        return $this->storage->url($disk, $path);
    }

    public function forMedia(Media $media, string $variant = 'main'): ?string
    {
        if ($variant === 'main' || $variant === 'full') {
            return $this->url($media->disk, $media->path);
        }

        if ($variant === 'original') {
            return $this->url($media->disk, $media->original_path ?: $media->path);
        }

        $row = $media->relationLoaded('variants')
            ? $media->variants->firstWhere('name', $variant)
            : $media->variants()->where('name', $variant)->first();

        if ($row instanceof MediaVariant) {
            return $this->url($media->disk, $row->path);
        }

        return $this->url($media->disk, $media->path);
    }

    /**
     * @return array<string, string|null>
     */
    public function variantUrls(Media $media): array
    {
        $urls = [
            'main' => $this->forMedia($media, 'main'),
            'original' => $this->forMedia($media, 'original'),
        ];

        foreach ($media->variants as $variant) {
            $urls[$variant->name] = $this->url($media->disk, $variant->path);
        }

        return $urls;
    }
}
