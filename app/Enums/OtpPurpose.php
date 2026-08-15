<?php

namespace App\Enums;

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
     * How the code is worded when delivered as free text.
     */
    public function message(string $code, int $minutes): string
    {
        return "رمز {$this->label()}: {$code}\nصالح لمدة {$minutes} دقيقة. لا تشاركه مع أحد.";
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
