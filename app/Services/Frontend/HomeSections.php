<?php

namespace App\Services\Frontend;

use App\Models\SystemSetting;

/**
 * Which home page sections are shown, stored as boolean rows in system_settings
 * under the `home_sections` group. A section with no row is shown, so a fresh
 * install renders the full page.
 */
class HomeSections
{
    public const GROUP = 'home_sections';

    /**
     * Every toggleable section, in page order.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'hero' => 'السلايدر الرئيسي (الهيرو)',
        'categories' => 'التصنيفات المميزة',
        'courses' => 'الدورات الشائعة',
        'team' => 'فريق العمل',
        'testimonials' => 'آراء الطلاب',
        'newsletter' => 'النشرة البريدية',
        'blog' => 'أحدث المقالات',
    ];

    /** @var array<string, bool>|null */
    private ?array $resolved = null;

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = SystemSetting::query()
            ->ofGroup(self::GROUP)
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value]);

        $visibility = [];

        foreach (array_keys(self::SECTIONS) as $key) {
            $visibility[$key] = (bool) $stored->get($key, true);
        }

        return $this->resolved = $visibility;
    }

    public function shows(string $section): bool
    {
        return $this->all()[$section] ?? false;
    }

    /**
     * Background classes for a content section. The page alternates plain and
     * patterned zones; computing them from the visible sections keeps the rhythm
     * intact when some are hidden. The hero has its own background.
     */
    public function zoneClasses(string $section): string
    {
        $visible = array_values(array_filter(
            array_keys(self::SECTIONS),
            fn (string $key) => $key !== 'hero' && $this->shows($key),
        ));

        $position = array_search($section, $visible, true);

        if ($position === false) {
            return 'home-zone home-zone--base';
        }

        return 'home-zone '.($position % 2 === 0 ? 'home-zone--base' : 'home-zone--alt')
            .($position === count($visible) - 1 ? ' home-zone--last' : '');
    }

    /**
     * Hide a section for this request only, e.g. when it has nothing to show,
     * so the zone backgrounds are computed without it.
     */
    public function suppress(string $section): void
    {
        $this->all();
        $this->resolved[$section] = false;
    }

    /**
     * @param  array<string, mixed>  $visibility
     */
    public function save(array $visibility): void
    {
        foreach (array_keys(self::SECTIONS) as $key) {
            if (array_key_exists($key, $visibility)) {
                SystemSetting::set($key, (bool) $visibility[$key], 'boolean', self::GROUP);
            }
        }

        $this->resolved = null;
    }

    /**
     * @return array<int, array{key: string, label: string, visible: bool}>
     */
    public function toAdminArray(): array
    {
        $visibility = $this->all();

        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'visible' => $visibility[$key]],
            array_keys(self::SECTIONS),
            self::SECTIONS,
        );
    }
}
