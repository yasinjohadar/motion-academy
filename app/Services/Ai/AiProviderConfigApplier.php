<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;

class AiProviderConfigApplier
{
    public const CACHE_KEY = 'ai.providers.settings.ids';

    public static function apply(): void
    {
        if (! self::tableReady()) {
            return;
        }

        $ids = Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return AiProvider::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->pluck('id')
                ->all();
        });

        if ($ids === []) {
            return;
        }

        $providers = AiProvider::query()
            ->whereIn('id', $ids)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            return;
        }

        $configMap = [];
        $purgeKeys = [];

        foreach ($providers as $provider) {
            $entry = $provider->toAiProviderConfig();
            $key = $provider->configKey();

            $configMap["ai.providers.{$key}"] = $entry;
            $purgeKeys[] = $key;

            if ($provider->is_default || ! isset($configMap["ai.providers.{$provider->driver}"])) {
                $configMap["ai.providers.{$provider->driver}"] = array_merge(
                    (array) config("ai.providers.{$provider->driver}", []),
                    $entry,
                );
                $purgeKeys[] = $provider->driver;
            }
        }

        $default = $providers->firstWhere('is_default', true) ?? $providers->first();

        if ($default instanceof AiProvider) {
            $configMap['ai.default'] = $default->configKey();
        }

        config($configMap);

        try {
            Ai::purge(array_values(array_unique($purgeKeys)));
        } catch (\Throwable) {
            // Manager may not be resolved yet during early boot.
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function refresh(): void
    {
        self::forget();
        self::apply();
    }

    /**
     * Apply a temporary in-memory provider config for connection probes.
     *
     * @param  array<string, mixed>  $providerConfig
     */
    public static function applyTemporary(string $name, array $providerConfig): void
    {
        config([
            "ai.providers.{$name}" => $providerConfig,
        ]);

        try {
            Ai::purge($name);
        } catch (\Throwable) {
            //
        }
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('ai_providers');
        } catch (\Throwable) {
            return false;
        }
    }
}
