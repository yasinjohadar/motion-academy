<?php

namespace App\Services\Smtp;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SmtpConfigApplier
{
    public const CACHE_KEY = 'smtp.default.settings';

    public static function apply(): void
    {
        if (! self::tableReady()) {
            return;
        }

        $setting = Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return SmtpSetting::getDefault();
        });

        if (! $setting instanceof SmtpSetting) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => array_merge(
                config('mail.mailers.smtp', []),
                $setting->toMailerConfig(),
            ),
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ]);
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

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('smtp_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
