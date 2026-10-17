<?php

namespace App\Services\Site;

use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteSettingsService
{
    public function __construct(private readonly AppStorageManager $storage) {}

    public function update(SiteSetting $setting, array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): SiteSetting
    {
        return DB::transaction(function () use ($setting, $data, $logo, $favicon) {
            if (! empty($data['remove_logo']) && $setting->logo_path) {
                $this->deleteStoredPath($setting->logo_path);
                $setting->logo_path = null;
            }

            if (! empty($data['remove_favicon']) && $setting->favicon_path) {
                $this->deleteStoredPath($setting->favicon_path);
                $setting->favicon_path = null;
            }

            if ($logo) {
                if ($setting->logo_path) {
                    $this->deleteStoredPath($setting->logo_path);
                }
                $path = $this->storage->storeUploadedFile('site', $logo, 'site');
                if ($path) {
                    $setting->logo_path = $path;
                }
            } elseif (! empty($data['logo_url'])) {
                if ($setting->logo_path) {
                    $this->deleteStoredPath($setting->logo_path);
                }
                $setting->logo_path = $data['logo_url'];
            }

            if ($favicon) {
                if ($setting->favicon_path) {
                    $this->deleteStoredPath($setting->favicon_path);
                }
                $path = $this->storage->storeUploadedFile('site', $favicon, 'site');
                if ($path) {
                    $setting->favicon_path = $path;
                }
            } elseif (! empty($data['favicon_url'])) {
                if ($setting->favicon_path) {
                    $this->deleteStoredPath($setting->favicon_path);
                }
                $setting->favicon_path = $data['favicon_url'];
            }

            $setting->fill([
                'site_name' => $data['site_name'],
                'site_name_en' => $data['site_name_en'] ?? null,
                'tagline' => $data['tagline'] ?? null,
                'primary_whatsapp' => $data['primary_whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'footer_text' => $data['footer_text'] ?? null,
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
                'seo_keywords' => $data['seo_keywords'] ?? null,
                'seo_og_image' => $data['seo_og_image'] ?? null,
                'twitter_handle' => filled($data['twitter_handle'] ?? null) ? '@'.ltrim($data['twitter_handle'], '@') : null,
                'google_site_verification' => $this->verificationCode($data['google_site_verification'] ?? null),
                'bing_site_verification' => $this->verificationCode($data['bing_site_verification'] ?? null),
                'allow_indexing' => (bool) ($data['allow_indexing'] ?? $setting->allow_indexing ?? true),
            ])->save();

            $this->syncSocialLinks($data['social_links'] ?? []);

            return $setting->fresh();
        });
    }

    /**
     * Search consoles hand out a whole tag (<meta name="…" content="CODE">);
     * people paste either that or the bare code. Keep only the code.
     */
    private function verificationCode(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $input, $match)) {
            return trim($match[1]);
        }

        return mb_substr(strip_tags($input), 0, 120);
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    public function syncSocialLinks(array $links): void
    {
        $keepIds = [];

        foreach (array_values($links) as $index => $link) {
            $payload = [
                'label' => $link['label'],
                'url' => $link['url'],
                'icon' => $link['icon'] ?: 'ri-link',
                'sort_order' => (int) ($link['sort_order'] ?? $index),
                'is_active' => filter_var($link['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            if (! empty($link['id'])) {
                $model = SocialLink::query()->find($link['id']);
                if ($model) {
                    $model->update($payload);
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = SocialLink::query()->create($payload);
            $keepIds[] = $created->id;
        }

        SocialLink::query()->whereNotIn('id', $keepIds)->delete();
    }

    private function deleteStoredPath(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        $this->storage->delete('site', $path);
    }
}
