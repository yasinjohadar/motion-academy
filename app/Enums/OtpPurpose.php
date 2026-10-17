<?php

namespace App\Enums;

use App\Services\Otp\OtpSettings;

enum OtpPurpose: string
{
    case Login = 'login';
    case PhoneVerification = 'phone_verification';
    case PasswordReset = 'password_reset';
    case SensitiveAction = 'sensitive_action';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'تسجيل الدخول',
            self::PhoneVerification => 'تأكيد رقم الهاتف',
            self::PasswordReset => 'إعادة تعيين كلمة المرور',
            self::SensitiveAction => 'تأكيد إجراء حسّاس',
        };
    }

    /**
     * How the code is worded when delivered as free text — the template the admin
     * set under Control panel → OTP, with {code}, {minutes}, {purpose}, {site}.
     */
    public function message(string $code, int $minutes): string
    {
        return app(OtpSettings::class)->message($this, $code, $minutes);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
