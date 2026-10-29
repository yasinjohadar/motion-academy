<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Services\Storage\AppStorageManager;
use Illuminate\Database\Eloquent\Model;

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
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_og_image',
        'twitter_handle',
        'google_site_verification',
        'bing_site_verification',
        'allow_indexing',
    ];

    protected function casts(): array
    {
        return [
            'allow_indexing' => 'boolean',
        ];
    }

    public static function current(): self
    {
        /*
         * Empty search array, not ['id' => 1]: this is a singleton table, so "the
         * first row" is the right lookup, and passing `id` mass-assigned a column
         * that is not fillable — silently discarded before, an exception under
         * Model::shouldBeStrict.
         */
        return static::query()->firstOrCreate(
            [],
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
        return app(AppStorageManager::class)->publicUrl('site', $path);
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
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'seo_og_image' => $this->seo_og_image,
            'twitter_handle' => $this->twitter_handle,
            'google_site_verification' => $this->google_site_verification,
            'bing_site_verification' => $this->bing_site_verification,
            'allow_indexing' => $this->allow_indexing ?? true,
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
