<?php

namespace Tests\Support;

use App\Enums\OtpPurpose;
use App\Support\Otp\DeliversOtpCode;

/**
 * Stands in for WhatsApp in auth-flow tests: keeps every code it is handed so a
 * test can type it in the way a user reading the message would.
 */
class CapturingOtpChannel implements DeliversOtpCode
{
    /** @var array<int, array{identifier: string, purpose: OtpPurpose, code: string, ttlMinutes: int}> */
    public static array $delivered = [];

    public function deliver(string $identifier, OtpPurpose $purpose, string $code, int $ttlMinutes): void
    {
        static::$delivered[] = compact('identifier', 'purpose', 'code', 'ttlMinutes');
    }

    public static function install(): void
    {
        static::$delivered = [];
        config()->set('otp.channel', static::class);
    }

    public static function lastCode(): ?string
    {
        return static::$delivered === [] ? null : end(static::$delivered)['code'];
    }
}
