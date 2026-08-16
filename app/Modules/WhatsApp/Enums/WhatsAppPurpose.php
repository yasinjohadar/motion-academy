<?php

namespace App\Modules\WhatsApp\Enums;

/**
 * Why a WhatsApp message is being sent.
 *
 * Drives two decisions: which provider handles it, and — on Meta — which approved
 * template to fall back to once the 24-hour customer service window has closed.
 */
enum WhatsAppPurpose: string
{
    case Campaign = 'campaign';
    case SystemNotification = 'system';
    case Otp = 'otp';
    case PasswordReset = 'password_reset';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Campaign => 'الحملات',
            self::SystemNotification => 'إشعارات النظام',
            self::Otp => 'رموز التحقق',
            self::PasswordReset => 'إعادة تعيين كلمة المرور',
            self::Manual => 'الإرسال اليدوي',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Campaign => 'رسائل الحملات الجماعية',
            self::SystemNotification => 'إشعارات تلقائية من النظام',
            self::Otp => 'رموز التحقق لمرة واحدة',
            self::PasswordReset => 'روابط ورموز استعادة الحساب',
            self::Manual => 'رسائل يرسلها المشرف من اللوحة',
        };
    }

    /**
     * Purposes the system initiates, so the recipient has almost never messaged first.
     * On Meta these effectively always need an approved template.
     */
    public function isBusinessInitiated(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
