<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Services\Storage\AppStorageManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiteSetting extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'site_name',
        'site_name_en',
        'tagline',
        'logo_path',
        'favicon_path',
        'primary_whatsapp',
        'email',
        'phone',
        'address',
        'footer_text',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'site_name' => config('app.name', 'لوحة التحكم'),
                'site_name_en' => config('app.name', 'Admin'),
            ]
        );
    }

    public function logoUrl(): ?string
    {
        return $this->resolvePublicPath($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->resolvePublicPath($this->favicon_path);
    }

    private function resolvePublicPath(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return app(AppStorageManager::class)->url('site', $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'site_name' => $this->site_name,
            'site_name_en' => $this->site_name_en,
            'tagline' => $this->tagline,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'favicon_path' => $this->favicon_path,
            'favicon_url' => $this->faviconUrl(),
            'primary_whatsapp' => $this->primary_whatsapp,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'footer_text' => $this->footer_text,
        ];
    }

    /**
     * Public payload for Inertia share / frontend.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'site_name' => $this->site_name,
            'site_name_en' => $this->site_name_en,
            'tagline' => $this->tagline,
            'logo_url' => $this->logoUrl(),
            'favicon_url' => $this->faviconUrl(),
            'primary_whatsapp' => $this->primary_whatsapp,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'footer_text' => $this->footer_text,
        ];
    }
}
