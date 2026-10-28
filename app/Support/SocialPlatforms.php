<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * The social platforms a team member or a course landing page can link to,
 * stored as `[{platform, url}]`.
 */
class SocialPlatforms
{
    /**
     * Label, Font Awesome icon (site pages) and brand colour (landing pages,
     * which load no icon font).
     *
     * @var array<string, array{label: string, icon: string, color: string}>
     */
    public const ALL = [
        'whatsapp' => ['label' => 'واتساب', 'icon' => 'fab fa-whatsapp', 'color' => '#25d366'],
        'telegram' => ['label' => 'تيليجرام', 'icon' => 'fab fa-telegram-plane', 'color' => '#229ed9'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram', 'color' => '#e1306c'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'color' => '#1877f2'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'fab fa-youtube', 'color' => '#ff0000'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'fab fa-tiktok', 'color' => '#111111'],
        'x' => ['label' => 'X', 'icon' => 'fab fa-x-twitter', 'color' => '#111111'],
        'snapchat' => ['label' => 'Snapchat', 'icon' => 'fab fa-snapchat-ghost', 'color' => '#f7c600'],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fab fa-linkedin-in', 'color' => '#0a66c2'],
        'github' => ['label' => 'GitHub', 'icon' => 'fab fa-github', 'color' => '#24292f'],
        'behance' => ['label' => 'Behance', 'icon' => 'fab fa-behance', 'color' => '#1769ff'],
        'dribbble' => ['label' => 'Dribbble', 'icon' => 'fab fa-dribbble', 'color' => '#ea4c89'],
        'vimeo' => ['label' => 'Vimeo', 'icon' => 'fab fa-vimeo-v', 'color' => '#1ab7ea'],
        'website' => ['label' => 'الموقع', 'icon' => 'fas fa-globe', 'color' => '#02ad8f'],
        'email' => ['label' => 'البريد', 'icon' => 'fas fa-envelope', 'color' => '#1a83c1'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, array $platform) => ['value' => $value, ...$platform],
            array_keys(self::ALL),
            self::ALL,
        );
    }

    /**
     * Stored links ready to render, unknown platforms and empty URLs dropped;
     * email addresses become mailto: links.
     *
     * @param  array<int, array{platform?: string, url?: string}>|null  $links
     * @return array<int, array{platform: string, label: string, icon: string, color: string, url: string}>
     */
    public static function buttons(?array $links): array
    {
        $buttons = [];

        foreach ($links ?? [] as $link) {
            $key = $link['platform'] ?? '';
            $platform = self::ALL[$key] ?? null;
            $url = (string) ($link['url'] ?? '');

            if ($platform === null || $url === '') {
                continue;
            }

            if ($key === 'email' && ! Str::startsWith($url, 'mailto:')) {
                $url = 'mailto:'.$url;
            }

            $buttons[] = ['platform' => $key, ...$platform, 'url' => $url];
        }

        return $buttons;
    }

    /**
     * Validation for a `{field}.*.url` list: a web URL, except email which
     * takes an address. Keeps `javascript:` and friends out of hrefs.
     */
    public static function validateUrls(Validator $validator, mixed $links, string $field): void
    {
        foreach ((array) $links as $index => $link) {
            $url = (string) ($link['url'] ?? '');
            $valid = ($link['platform'] ?? null) === 'email'
                ? filter_var(preg_replace('/^mailto:/', '', $url), FILTER_VALIDATE_EMAIL) !== false
                : preg_match('/^https?:\/\/\S+$/i', $url) === 1;

            if ($url !== '' && ! $valid) {
                $validator->errors()->add("{$field}.{$index}.url", 'الرابط غير صالح لهذه المنصة.');
            }
        }
    }
}
