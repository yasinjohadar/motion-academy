<?php

namespace App\Enums;

use App\Notifications\Channels\WhatsAppChannel;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Delivery channels a notification may use.
 *
 * The `value` is the key stored in `notification_preferences.channel` and used in
 * `config/notifications.php`. The `driver()` is what Laravel's ChannelManager
 * understands — either a built-in driver name or a custom channel class name.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Broadcast = 'broadcast';
    case Mail = 'mail';
    case WhatsApp = 'whatsapp';
    case WebPush = 'webpush';

    /**
     * The driver name or channel class Laravel should resolve for this channel.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Database => 'database',
            self::Broadcast => 'broadcast',
            self::Mail => 'mail',
            self::WhatsApp => WhatsAppChannel::class,
            self::WebPush => WebPushChannel::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Database => 'داخل التطبيق',
            self::Broadcast => 'فوري (لحظي)',
            self::Mail => 'البريد الإلكتروني',
            self::WhatsApp => 'واتساب',
            self::WebPush => 'إشعارات المتصفح',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Database => 'ri-notification-3-line',
            self::Broadcast => 'ri-flashlight-line',
            self::Mail => 'ri-mail-line',
            self::WhatsApp => 'ri-whatsapp-line',
            self::WebPush => 'ri-computer-line',
        };
    }

    /**
     * Channels the user may switch off. `database` is the source of truth for the
     * notification centre, so it is always on.
     */
    public function isOptional(): bool
    {
        return $this !== self::Database;
    }

    /**
     * The queue each channel is dispatched on, for `viaQueues()`.
     */
    public function queue(): string
    {
        return match ($this) {
            self::Database => 'default',
            self::Broadcast => 'broadcast',
            self::Mail => 'mail',
            self::WhatsApp => 'whatsapp',
            self::WebPush => 'default',
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
