<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

class MediaService
{
    public function __construct(
        private readonly AppStorageManager $storage,
        private readonly ImageProcessor $processor,
        private readonly MediaUrlResolver $urls,
    ) {}

    public function upload(UploadedFile $file, ?User $user = null, ?string $title = null, ?string $alt = null): Media
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $originalName = $file->getClientOriginalName();
        $disk = (string) config('media.logical_disk', 'media');
        $baseDir = trim((string) config('media.directory', 'library'), '/');
        $folder = $baseDir.'/'.now()->format('Y/m');
        $uuid = (string) Str::uuid();

        $tempPath = $file->getRealPath();
        if (! $tempPath || ! is_file($tempPath)) {
            throw new RuntimeException('تعذر قراءة الملف المرفوع.');
        }

        $passthrough = in_array($mime, config('media.passthrough_mimes', []), true)
            || $extension === 'svg'
            || $extension === 'gif';

        return DB::transaction(function () use (
            $file,
            $user,
            $title,
            $alt,
            $mime,
            $extension,
            $originalName,
            $disk,
            $folder,
            $uuid,
            $tempPath,
            $passthrough,
        ) {
            $media = new Media([
                'uuid' => $uuid,
                'disk' => $disk,
                'filename' => $originalName,
                'mime' => $mime,
                'extension' => $extension,
                'title' => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
                'alt' => $alt,
                'uploaded_by' => $user?->id,
            ]);

            if ($passthrough) {
                return $this->storePassthrough($media, $file, $tempPath, $folder, $uuid, $extension, $mime);
            }

            return $this->storeProcessed($media, $file, $tempPath, $folder, $uuid, $extension);
        });
    }

    public function updateMeta(Media $media, array $data): Media
    {
        $media->fill(collect($data)->only(['alt', 'title', 'caption', 'description'])->all());
        $media->save();

        return $media->fresh(['variants', 'uploader']);
    }

    public function delete(Media $media): void
    {
        $paths = collect([$media->path, $media->original_path])
            ->merge($media->variants()->pluck('path'))
            ->filter()
            ->unique()
            ->values();

        foreach ($paths as $path) {
            $this->storage->delete($media->disk, $path);
        }

        $media->forceDelete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(Media $media): array
    {
        $media->loadMissing(['variants', 'uploader']);
        $urls = $this->urls->variantUrls($media);

        return [
            'id' => $media->id,
            // Ownership-aware (edit-any / delete-any), for the buttons on each item.
            'can' => [
                'update' => Gate::allows('update', $media),
                'delete' => Gate::allows('delete', $media),
            ],
            'uuid' => $media->uuid,
            'filename' => $media->filename,
            'mime' => $media->mime,
            'extension' => $media->extension,
            'size' => $media->size,
            'human_size' => $this->humanSize((int) $media->size),
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->alt,
            'title' => $media->title,
            'caption' => $media->caption,
            'description' => $media->description,
            'url' => $urls['main'] ?? null,
            'urls' => $urls,
            'thumb_url' => $urls['thumb'] ?? ($urls['main'] ?? null),
            'uploaded_by' => $media->uploader?->name,
            'created_at' => optional($media->created_at)->toDateTimeString(),
            'updated_at' => optional($media->updated_at)->toDateTimeString(),
        ];
    }

    private function storeProcessed(
        Media $media,
        UploadedFile $file,
        string $tempPath,
        string $folder,
        string $uuid,
        string $originalExtension,
    ): Media {
        $processed = $this->processor->processRaster($tempPath);
        $main = $processed['main'];

        $mainPath = "{$folder}/{$uuid}.{$main['extension']}";
        if (! $this->storage->put($media->disk, $mainPath, $main['binary'])) {
            throw new RuntimeException('فشل حفظ الصورة المعالجة.');
        }

        $originalPath = null;
        if (config('media.keep_original', true)) {
            $originalPath = "{$folder}/{$uuid}.original.{$originalExtension}";
            $stored = $this->storage->storeUploadedFileAs($media->disk, $file, $originalPath);
            if ($stored === false) {
                $this->storage->delete($media->disk, $mainPath);
                throw new RuntimeException('فشل حفظ الملف الأصلي.');
            }
            $originalPath = $stored;
        }

        $media->fill([
            'path' => $mainPath,
            'original_path' => $originalPath,
            'mime' => $main['mime'],
            'extension' => $main['extension'],
            'size' => $main['size'],
            'width' => $main['width'],
            'height' => $main['height'],
        ]);
        $media->save();

        foreach ($processed['variants'] as $name => $variant) {
            $variantPath = "{$folder}/{$uuid}-{$name}.{$variant['extension']}";
            if (! $this->storage->put($media->disk, $variantPath, $variant['binary'])) {
                continue;
            }

            $media->variants()->create([
                'name' => $name,
                'path' => $variantPath,
                'mime' => $variant['mime'],
                'size' => $variant['size'],
                'width' => $variant['width'],
                'height' => $variant['height'],
            ]);
        }

        return $media->fresh(['variants', 'uploader']);
    }

    private function storePassthrough(
        Media $media,
        UploadedFile $file,
        string $tempPath,
        string $folder,
        string $uuid,
        string $extension,
        string $mime,
    ): Media {
        $path = "{$folder}/{$uuid}.{$extension}";
        $stored = $this->storage->storeUploadedFileAs($media->disk, $file, $path);
        if ($stored === false) {
            throw new RuntimeException('فشل حفظ الملف.');
        }

        $width = null;
        $height = null;
        if ($mime !== 'image/svg+xml' && $extension !== 'svg') {
            try {
                $dims = $this->processor->dimensions($tempPath);
                $width = $dims['width'];
                $height = $dims['height'];
            } catch (\Throwable) {
                // ignore
            }
        }

        $media->fill([
            'path' => $stored,
            'original_path' => null,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
        ]);
        $media->save();

        if ($mime === 'image/gif' || $extension === 'gif') {
            $thumb = $this->processor->makeThumbOnly($tempPath);
            if ($thumb) {
                $thumbPath = "{$folder}/{$uuid}-thumb.webp";
                if ($this->storage->put($media->disk, $thumbPath, $thumb['binary'])) {
                    $media->variants()->create([
                        'name' => 'thumb',
                        'path' => $thumbPath,
                        'mime' => $thumb['mime'],
                        'size' => $thumb['size'],
                        'width' => $thumb['width'],
                        'height' => $thumb['height'],
                    ]);
                }
            }
        }

        return $media->fresh(['variants', 'uploader']);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
