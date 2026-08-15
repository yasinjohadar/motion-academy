<?php

namespace App\Support\Otp;

use App\Enums\OtpPurpose;

/**
 * Carries a one-time code to its recipient.
 *
 * OtpService used to depend on SendWhatsAppMessage directly, which meant the core
 * could not issue a code without the WhatsApp module installed — and that a project
 * wanting codes over SMS or email had to edit core code to get them.
 *
 * Deliberately not routed through the notification system: an OTP goes to a raw
 * identifier rather than a notifiable, and it must ignore channel preferences
 * entirely, so the machinery there would be bypassed at every step anyway.
 *
 * Implementations live in the module that owns the transport. The configured one is
 * named in `config('otp.channel')`.
 */
interface DeliversOtpCode
{
    /**
     * Send the code, or throw.
     *
     * Returning quietly on failure would leave OtpService recording an issued code
     * that never arrived, and the user waiting for a message no one sent.
     */
    public function deliver(string $identifier, OtpPurpose $purpose, string $code, int $ttlMinutes): void;
}
