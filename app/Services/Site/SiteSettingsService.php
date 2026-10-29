<?php

namespace App\Services\Site;

use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteSettingsService
{
    public function __construct(private readonly AppStorageManager $storage) {}

    public function update(SiteSetting $setting, array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): SiteSetting
    {
        return DB::transaction(function () use ($setting, $data, $logo, $favicon) {
            // Old files are deleted only once the new values are committed: a failed
            // upload or a rolled-back save must never cost the site its current logo.
            $obsolete = [];

            $setting->logo_path = $this->resolveFile(
                'logo', $setting->logo_path, $logo, $data['logo_url'] ?? null, ! empty($data['remove_logo']), $obsolete,
            );
            $setting->favicon_path = $this->resolveFile(
                'favicon', $setting->favicon_path, $favicon, $data['favicon_url'] ?? null, ! empty($data['remove_favicon']), $obsolete,
            );

            DB::afterCommit(function () use ($obsolete) {
                foreach ($obsolete as $path) {
                    $this->storage->delete('site', $path);
                }
            });

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

    /**
     * The new value of a file column. Previous files that the new value replaces
     * are added to $obsolete for deletion after commit.
     *
     * @param  array<int, string>  $obsolete
     */
    private function resolveFile(string $field, ?string $current, ?UploadedFile $file, ?string $url, bool $remove, array &$obsolete): ?string
    {
        if ($file) {
            $path = $this->storage->storeUploadedFile('site', $file, 'site');

            if ($path === false) {
                throw ValidationException::withMessages([$field => 'تعذّر رفع الملف. لم يتم تغيير الملف الحالي.']);
            }

            $next = $path;
        } elseif (filled($url)) {
            $next = $url;
        } elseif ($remove) {
            $next = null;
        } else {
            return $current;
        }

        if ($current !== null && $current !== $next) {
            $obsolete[] = $current;
        }

        return $next;
    }
}
