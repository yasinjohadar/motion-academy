<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\SystemSetting;
use Exception;
use Illuminate\Support\Facades\Crypt;

class WhatsAppSettingsService
{
    public const SUPPORTED_PROVIDERS = ['evolution', 'meta', 'flaxxa'];

    public const DEFAULT_PROVIDER = 'evolution';

    /** Confirm against your Meta app dashboard; Meta deprecates versions on a schedule. */
    public const DEFAULT_META_API_VERSION = 'v21.0';

    public const DEFAULT_FLAXXA_BASE_URL = 'https://wapi.flaxxa.com';

    /** Never stored or returned in clear text. */
    public const ENCRYPTED_KEYS = [
        'evolution_api_key',
        'evolution_webhook_secret',
        'meta_access_token',
        'meta_app_secret',
        'meta_verify_token',
        'flaxxa_token',
    ];

    public function getSettings(): array
    {
        $this->initializeDefaults();

        $settings = SystemSetting::query()
            ->where('group', 'whatsapp')
            ->get()
            ->keyBy('key')
            ->map(fn ($setting) => $setting->getRawOriginal('value') ?? $setting->attributes['value'] ?? null)
            ->toArray();

        // Re-read via model accessors for typed values where needed
        $typed = SystemSetting::query()
            ->where('group', 'whatsapp')
            ->get()
            ->keyBy('key');

        $raw = fn (string $key, $default = null) => $typed->get($key)?->getRawOriginal('value')
            ?? ($settings[$key] ?? $default);

        return [
            'whatsapp_enabled' => filter_var($raw('whatsapp_enabled', false), FILTER_VALIDATE_BOOLEAN),
            // Read from storage rather than hard-coded: this key used to be the literal
            // 'evolution', which made every provider setting in the panel decorative.
            'whatsapp_provider' => $this->normalizeProvider((string) ($raw('whatsapp_provider', self::DEFAULT_PROVIDER) ?: self::DEFAULT_PROVIDER)),
            'timeout' => (int) ($raw('timeout', 30) ?: 30),
            'delay_between_messages' => (int) ($raw('delay_between_messages', 3) ?: 3),
            'delay_between_broadcasts' => (int) ($raw('delay_between_broadcasts', 5) ?: 5),
            'max_messages_per_minute' => (int) ($raw('max_messages_per_minute', 20) ?: 20),
            'random_delay_enabled' => filter_var($raw('random_delay_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'min_delay' => (int) ($raw('min_delay', 2) ?: 2),
            'max_delay' => (int) ($raw('max_delay', 5) ?: 5),
            'evolution_base_url' => $this->normalizeServiceUrl((string) ($raw('evolution_base_url', '') ?: '')),
            'evolution_api_key' => $this->decryptIfEncrypted((string) ($raw('evolution_api_key', '') ?: '')),
            'evolution_instance_name' => (string) ($raw('evolution_instance_name', '') ?: ''),
            'evolution_rotation_enabled' => filter_var($raw('evolution_rotation_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'evolution_webhook_secret' => $this->decryptIfEncrypted((string) ($raw('evolution_webhook_secret', '') ?: '')),
            'evolution_webhook_base_url' => rtrim(trim((string) ($raw('evolution_webhook_base_url', '') ?: '')), '/'),

            // Meta WhatsApp Cloud API
            'meta_phone_number_id' => (string) ($raw('meta_phone_number_id', '') ?: ''),
            'meta_waba_id' => (string) ($raw('meta_waba_id', '') ?: ''),
            'meta_access_token' => $this->decryptIfEncrypted((string) ($raw('meta_access_token', '') ?: '')),
            // A setting, not a constant — pin it to whatever version your Meta app targets.
            'meta_api_version' => (string) ($raw('meta_api_version', self::DEFAULT_META_API_VERSION) ?: self::DEFAULT_META_API_VERSION),
            'meta_app_secret' => $this->decryptIfEncrypted((string) ($raw('meta_app_secret', '') ?: '')),
            'meta_verify_token' => $this->decryptIfEncrypted((string) ($raw('meta_verify_token', '') ?: '')),

            // Flaxxa Wapi. `?:` and not `??` — initializeDefaults() seeds an empty
            // string, so a null-coalesce would hand the provider a blank base URL.
            'flaxxa_base_url' => $this->normalizeServiceUrl(
                (string) ($raw('flaxxa_base_url', self::DEFAULT_FLAXXA_BASE_URL) ?: self::DEFAULT_FLAXXA_BASE_URL)
            ),
            'flaxxa_token' => $this->decryptIfEncrypted((string) ($raw('flaxxa_token', '') ?: '')),
        ];
    }

    /**
     * A typo in the settings table must not take WhatsApp down entirely.
     */
    protected function normalizeProvider(string $provider): string
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER;
    }

    public function updateSettings(array $newSettings): void
    {
        foreach ($newSettings as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true) && ! empty($value)) {
                $value = Crypt::encryptString((string) $value);
            }

            if (($key === 'evolution_base_url' || $key === 'flaxxa_base_url') && is_string($value)) {
                $value = $this->normalizeServiceUrl($value);
            }

            if ($key === 'evolution_webhook_base_url' && is_string($value)) {
                $value = rtrim(trim($value), '/');
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            SystemSetting::set($key, $value, 'string', 'whatsapp');
        }
    }

    public function initializeDefaults(): void
    {
        $defaults = [
            'whatsapp_enabled' => '1',
            'whatsapp_provider' => 'evolution',
            'timeout' => '30',
            'delay_between_messages' => '3',
            'delay_between_broadcasts' => '5',
            'max_messages_per_minute' => '20',
            'random_delay_enabled' => 'true',
            'min_delay' => '2',
            'max_delay' => '5',
            'evolution_base_url' => '',
            'evolution_api_key' => '',
            'evolution_instance_name' => '',
            'evolution_rotation_enabled' => 'true',
            'evolution_webhook_secret' => '',
            'evolution_webhook_base_url' => '',
            'meta_phone_number_id' => '',
            'meta_waba_id' => '',
            'meta_access_token' => '',
            'meta_api_version' => self::DEFAULT_META_API_VERSION,
            'meta_app_secret' => '',
            'meta_verify_token' => '',
            'flaxxa_base_url' => self::DEFAULT_FLAXXA_BASE_URL,
            'flaxxa_token' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (! SystemSetting::byKey($key)->ofGroup('whatsapp')->exists()) {
                SystemSetting::set($key, $value, 'string', 'whatsapp');
            }
        }
    }

    /**
     * Config for a provider. Defaults to the active one.
     *
     * Previously returned Evolution keys unconditionally, so any second provider
     * received a config it could not use.
     *
     * @return array<string, mixed>
     */
    public function getProviderConfig(?string $provider = null): array
    {
        $settings = $this->getSettings();
        $provider ??= $settings['whatsapp_provider'];

        return match ($provider) {
            'meta' => [
                'phone_number_id' => $settings['meta_phone_number_id'],
                'waba_id' => $settings['meta_waba_id'],
                'access_token' => $settings['meta_access_token'],
                'api_version' => $settings['meta_api_version'],
                'timeout' => $settings['timeout'],
            ],
            'flaxxa' => [
                'base_url' => $settings['flaxxa_base_url'],
                'token' => $settings['flaxxa_token'],
                'timeout' => $settings['timeout'],
            ],
            default => [
                'base_url' => $settings['evolution_base_url'],
                'api_key' => $settings['evolution_api_key'],
                'instance_name' => $settings['evolution_instance_name'],
            ],
        };
    }

    /**
     * Per-purpose routing overrides stored as JSON, layered over config/whatsapp.php
     * by WhatsAppRouter. Kept as one row so adding a purpose needs no migration.
     *
     * @return array<string, mixed>
     */
    public function getRoutingOverrides(): array
    {
        $raw = SystemSetting::byKey('whatsapp_routing')->ofGroup('whatsapp')->first();
        $decoded = json_decode((string) ($raw?->getRawOriginal('value') ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $routing
     */
    public function updateRoutingOverrides(array $routing): void
    {
        SystemSetting::set('whatsapp_routing', json_encode($routing, JSON_UNESCAPED_UNICODE), 'string', 'whatsapp');
    }

    public function getDelaySettings(): array
    {
        $settings = $this->getSettings();

        return [
            'delay_between_messages' => $settings['delay_between_messages'],
            'delay_between_broadcasts' => $settings['delay_between_broadcasts'],
            'max_messages_per_minute' => $settings['max_messages_per_minute'],
            'random_delay_enabled' => $settings['random_delay_enabled'],
            'min_delay' => $settings['min_delay'],
            'max_delay' => $settings['max_delay'],
        ];
    }

    public function calculateDelay(?int $customDelay = null): int
    {
        $delaySettings = $this->getDelaySettings();
        $baseDelay = $customDelay ?? $delaySettings['delay_between_messages'];

        if ($delaySettings['random_delay_enabled']) {
            $min = $delaySettings['min_delay'];
            $max = max($min, $delaySettings['max_delay']);

            return $baseDelay + random_int($min, $max);
        }

        return $baseDelay;
    }

    /**
     * Safe payload for Inertia (never includes secrets in plain if empty markers).
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $settings = $this->getSettings();

        return [
            'whatsapp_enabled' => $settings['whatsapp_enabled'],
            'whatsapp_provider' => $settings['whatsapp_provider'],
            'evolution_base_url' => $settings['evolution_base_url'],
            'evolution_instance_name' => $settings['evolution_instance_name'],
            'evolution_rotation_enabled' => $settings['evolution_rotation_enabled'],
            'evolution_webhook_base_url' => $settings['evolution_webhook_base_url'],
            'has_api_key' => $settings['evolution_api_key'] !== '',
            'has_webhook_secret' => $settings['evolution_webhook_secret'] !== '',
            'delay_between_messages' => $settings['delay_between_messages'],
            'delay_between_broadcasts' => $settings['delay_between_broadcasts'],
            'max_messages_per_minute' => $settings['max_messages_per_minute'],
            'random_delay_enabled' => $settings['random_delay_enabled'],
            'min_delay' => $settings['min_delay'],
            'max_delay' => $settings['max_delay'],

            // Meta. Tokens and secrets are reported as presence flags only — their
            // values must never reach the browser, not even to prefill a field.
            'meta_phone_number_id' => $settings['meta_phone_number_id'],
            'meta_waba_id' => $settings['meta_waba_id'],
            'meta_api_version' => $settings['meta_api_version'],
            'has_meta_access_token' => $settings['meta_access_token'] !== '',
            'has_meta_app_secret' => $settings['meta_app_secret'] !== '',
            'has_meta_verify_token' => $settings['meta_verify_token'] !== '',

            'flaxxa_base_url' => $settings['flaxxa_base_url'],
            'has_flaxxa_token' => $settings['flaxxa_token'] !== '',
        ];
    }

    protected function normalizeServiceUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, '/')) {
            $url = substr($url, 1);
        }

        return rtrim($url, '/');
    }

    protected function decryptIfEncrypted(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Exception) {
            return $value;
        }
    }
}
