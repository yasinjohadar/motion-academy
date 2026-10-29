<?php

namespace App\Models\Concerns;

use App\Services\Storage\AppStorageManager;

/**
 * An `avatar` column holding either an uploaded path on the model's IMAGE_DISK
 * or an absolute / site-relative URL picked from the media library.
 */
trait HasPublicAvatar
{
    public function hasUploadedAvatar(): bool
    {
        return AppStorageManager::isStoredPath($this->avatar);
    }

    public function avatarUrl(): ?string
    {
        return app(AppStorageManager::class)->publicUrl(self::IMAGE_DISK, $this->avatar);
    }
}
