<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Backups = 'backups';
    case WhatsApp = 'whatsapp';
    case Blog = 'blog';
    case Users = 'users';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Backups => 'النسخ الاحتياطي',
            self::WhatsApp => 'واتساب',
            self::Blog => 'المدونة',
            self::Users => 'المستخدمون والصلاحيات',
            self::System => 'النظام',
        };
    }

    /**
     * Remix Icon class, matching the icons already used in the admin sidebar.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Backups => 'ri-database-2-line',
            self::WhatsApp => 'ri-whatsapp-line',
            self::Blog => 'ri-article-line',
            self::Users => 'ri-group-line',
            self::System => 'ri-settings-4-line',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Backups => 'admin-badge-info',
            self::WhatsApp => 'admin-badge-success',
            self::Blog => 'admin-badge-warning',
            self::Users => 'admin-badge-info',
            self::System => 'admin-badge-muted',
        };
    }

    /**
     * Channels enabled for this category when the user has no explicit preference.
     *
     * @return array<int, NotificationChannel>
     */
    public function defaultChannels(): array
    {
        $configured = config('notifications.defaults.'.$this->value);

        if (! is_array($configured)) {
            return [NotificationChannel::Database, NotificationChannel::Broadcast];
        }

        return array_values(array_filter(array_map(
            fn (string $channel): ?NotificationChannel => NotificationChannel::tryFrom($channel),
            $configured
        )));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
