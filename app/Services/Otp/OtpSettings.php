<?php

namespace App\Services\Otp;

use App\Enums\OtpPurpose;
use App\Models\SystemSetting;
use App\Support\Otp\DeliversOtpCode;

/**
 * Admin-editable OTP settings (Control panel → OTP), stored in `system_settings`
 * under the "otp" group, each falling back to config/otp.php.
 */
class OtpSettings
{
    public const GROUP = 'otp';

    public const DEFAULT_TEMPLATE = "رمز {purpose} في {site}: {code}\nصالح لمدة {minutes} دقائق. لا تشاركه مع أحد.";

    /** @var array<string, array{type: string, default: mixed}> */
    private const KEYS = [
        'login_enabled' => ['type' => 'boolean', 'default' => true],
        'reset_enabled' => ['type' => 'boolean', 'default' => true],
        'message_template' => ['type' => 'string', 'default' => null],
        'ttl_minutes' => ['type' => 'integer', 'default' => null],
        'max_attempts' => ['type' => 'integer', 'default' => null],
        'resend_cooldown_seconds' => ['type' => 'integer', 'default' => 60],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array{login_enabled: bool, reset_enabled: bool, message_template: string, ttl_minutes: int, max_attempts: int, resend_cooldown_seconds: int}
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored = SystemSetting::query()->ofGroup(self::GROUP)->get()->keyBy('key');

            $this->cache = [];
            foreach (self::KEYS as $key => $meta) {
                $this->cache[$key] = $stored->has($key) ? $stored[$key]->value : $meta['default'];
            }
        }

        return [
            'login_enabled' => (bool) $this->cache['login_enabled'],
            'reset_enabled' => (bool) $this->cache['reset_enabled'],
            'message_template' => filled($this->cache['message_template']) ? (string) $this->cache['message_template'] : self::DEFAULT_TEMPLATE,
            'ttl_minutes' => max(1, min(30, (int) ($this->cache['ttl_minutes'] ?? config('otp.ttl_minutes', 10)))),
            'max_attempts' => max(1, min(10, (int) ($this->cache['max_attempts'] ?? config('otp.max_attempts', 5)))),
            'resend_cooldown_seconds' => max(15, min(600, (int) $this->cache['resend_cooldown_seconds'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        foreach (self::KEYS as $key => $meta) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key, 'group' => self::GROUP],
                ['type' => $meta['type'], 'value' => $values[$key]],
            );
        }

        $this->cache = null;
    }

    public function ttlMinutes(): int
    {
        return $this->all()['ttl_minutes'];
    }

    public function maxAttempts(): int
    {
        return $this->all()['max_attempts'];
    }

    public function resendCooldown(): int
    {
        return $this->all()['resend_cooldown_seconds'];
    }

    public function message(OtpPurpose $purpose, string $code, int $minutes): string
    {
        return strtr($this->all()['message_template'], [
            '{code}' => $code,
            '{minutes}' => (string) $minutes,
            '{purpose}' => $purpose->label(),
            '{site}' => (string) config('app.name'),
        ]);
    }

    /**
     * Whether a delivery channel is configured at all — false when the WhatsApp
     * module has been removed and nothing replaced it.
     */
    public function channelReady(): bool
    {
        $class = config('otp.channel');

        return is_string($class) && class_exists($class) && is_subclass_of($class, DeliversOtpCode::class);
    }

    public function isAvailable(OtpPurpose $purpose): bool
    {
        $settings = $this->all();

        $enabled = match ($purpose) {
            OtpPurpose::Login => $settings['login_enabled'],
            OtpPurpose::PasswordReset => $settings['reset_enabled'],
            default => true,
        };

        return $enabled && $this->channelReady();
    }
}
