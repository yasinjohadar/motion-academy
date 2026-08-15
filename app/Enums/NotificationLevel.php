<?php

namespace App\Enums;

enum NotificationLevel: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'معلومة',
            self::Success => 'نجاح',
            self::Warning => 'تحذير',
            self::Danger => 'خطأ',
        };
    }

    /**
     * Matches the `admin-badge-*` classes used across the admin tables.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Info => 'admin-badge-info',
            self::Success => 'admin-badge-success',
            self::Warning => 'admin-badge-warning',
            self::Danger => 'admin-badge-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'ri-information-line',
            self::Success => 'ri-checkbox-circle-line',
            self::Warning => 'ri-alert-line',
            self::Danger => 'ri-close-circle-line',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
