<?php

namespace App\Models\Concerns;

use App\Services\Storage\AppStorageManager;
use Illuminate\Support\Str;

/**
 * An `avatar` column holding either an uploaded path on the model's IMAGE_DISK
 * or an absolute / site-relative URL picked from the media library.
 */
trait HasPublicAvatar
{
    public function hasUploadedAvatar(): bool
    {
        return filled($this->avatar) && ! Str::startsWith((string) $this->avatar, ['http://', 'https://', '/']);
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar)) {
            return null;
        }

        if (! $this->hasUploadedAvatar()) {
            return $this->avatar;
        }

        try {
            return app(AppStorageManager::class)->url(self::IMAGE_DISK, $this->avatar) ?? asset('storage/'.$this->avatar);
        } catch (\Throwable) {
            return asset('storage/'.$this->avatar);
        }
    }
}
