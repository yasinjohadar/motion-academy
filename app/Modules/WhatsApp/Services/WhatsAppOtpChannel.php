<?php

namespace App\Modules\WhatsApp\Services;

use App\Enums\OtpPurpose;
use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Support\Otp\DeliversOtpCode;

/**
 * Delivers one-time codes over WhatsApp.
 *
 * This class is the WhatsApp module's side of the OTP contract, and the only place
 * that knows an OTP is a `WhatsAppPurpose::Otp` send whose code fills the approved
 * authentication template's first parameter. OtpService used to carry that knowledge
 * itself, which tied the core's OTP feature to this module.
 */
class WhatsAppOtpChannel implements DeliversOtpCode
{
    public function __construct(private SendWhatsAppMessage $whatsapp) {}

    public function deliver(string $identifier, OtpPurpose $purpose, string $code, int $ttlMinutes): void
    {
        $this->whatsapp->sendText(
            $identifier,
            $purpose->message($code, $ttlMinutes),
            previewUrl: false,
            // On the official providers the recipient has almost never written to the
            // business, so the window is closed and only the template will be accepted.
            purpose: WhatsAppPurpose::Otp,
            templateParameters: [$code],
        );
    }
}
