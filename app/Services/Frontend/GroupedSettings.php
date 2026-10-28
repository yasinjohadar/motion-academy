<?php

namespace App\Services\Frontend;

use App\Models\SystemSetting;

/**
 * A typed block of settings stored as rows in system_settings under one group.
 * Missing rows fall back to the declared defaults, so a fresh install behaves
 * sensibly before anyone saves.
 */
abstract class GroupedSettings
{
    public const GROUP = '';

    /**
     * @var array<string, array{type: string, default: bool|int|string}>
     */
    public const FIELDS = [];

    /**
     * @return array<string, bool|int|string>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()
            ->ofGroup(static::GROUP)
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value]);

        $settings = [];

        foreach (static::FIELDS as $key => $field) {
            $value = $stored->get($key) ?? $field['default'];

            $settings[$key] = match ($field['type']) {
                'boolean' => (bool) $value,
                'integer' => (int) $value,
                default => (string) $value,
            };
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (static::FIELDS as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            SystemSetting::set($key, $values[$key], $field['type'], static::GROUP);
        }
    }

    /**
     * Store the default for every setting that has no row yet, keeping saved values.
     */
    public function seedDefaults(): void
    {
        $existing = SystemSetting::query()->ofGroup(static::GROUP)->pluck('key')->all();

        $this->save(array_diff_key(
            array_map(fn (array $field) => $field['default'], static::FIELDS),
            array_flip($existing),
        ));
    }
}
