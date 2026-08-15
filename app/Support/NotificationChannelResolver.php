<?php

namespace App\Support;

use App\Enums\NotificationChannel;
use App\Models\EvolutionInstance;
use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Services\WhatsApp\ProviderCapabilities;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Services\WhatsApp\WhatsAppSettingsService;

/**
 * Decides which channels a notification actually goes out on.
 *
 * Three layers must all agree:
 *   1. `$notification->supportedChannels()` — what this notification type allows.
 *   2. The notifiable's stored preferences (falling back to config defaults).
 *   3. Real availability — no SMTP configured, no phone number, broadcasting off, etc.
 *
 * `database` bypasses layers 2 and 3: it is the notification centre's source of truth.
 */
class NotificationChannelResolver
{
    /**
     * Driver names / channel classes to hand back to Laravel's `via()`.
     *
     * @return array<int, string>
     */
    public function resolve(object $notifiable, BaseNotification $notification): array
    {
        $channels = array_filter(
            $notification->supportedChannels(),
            fn (NotificationChannel $channel): bool => $this->shouldUse($channel, $notifiable, $notification)
        );

        return array_values(array_unique(array_map(
            fn (NotificationChannel $channel): string => $channel->driver(),
            $channels
        )));
    }

    protected function shouldUse(
        NotificationChannel $channel,
        object $notifiable,
        BaseNotification $notification
    ): bool {
        // The in-app record is never optional.
        if (! $channel->isOptional()) {
            return true;
        }

        if (! $this->isAvailable($channel, $notifiable)) {
            return false;
        }

        // Transactional security messages (password reset, OTP) ignore preferences:
        // a toggle must never be able to lock someone out of their own account.
        // `supportedChannels()` and availability still apply.
        if ($notification->bypassesPreferences()) {
            return true;
        }

        return $this->isEnabledForNotifiable($channel, $notifiable, $notification);
    }

    /**
     * Is the channel usable at all right now? Guards against silently queueing
     * deliveries that can only fail.
     */
    public function isAvailable(NotificationChannel $channel, object $notifiable): bool
    {
        return match ($channel) {
            NotificationChannel::Database => true,

            NotificationChannel::Broadcast => (bool) config('notifications.realtime.enabled')
                && config('broadcasting.default') !== 'null',

            NotificationChannel::Mail => filled($notifiable->routeNotificationFor('mail'))
                && $this->hasActiveMailer(),

            NotificationChannel::WhatsApp => filled($notifiable->routeNotificationFor('whatsapp'))
                && $this->hasUsableWhatsAppProvider(),

            NotificationChannel::WebPush => $this->hasPushSubscription($notifiable),
        };
    }

    protected function isEnabledForNotifiable(
        NotificationChannel $channel,
        object $notifiable,
        BaseNotification $notification
    ): bool {
        $category = $notification->category();

        $defaults = array_map(
            fn (NotificationChannel $default): string => $default->value,
            $category->defaultChannels()
        );

        if (! $notifiable instanceof User) {
            return in_array($channel->value, $defaults, true);
        }

        $stored = NotificationPreference::mapForUser($notifiable);

        return $stored[$category->value.'.'.$channel->value]
            ?? in_array($channel->value, $defaults, true);
    }

    protected function hasActiveMailer(): bool
    {
        if (config('mail.default') === 'array' || config('mail.default') === 'log') {
            return true;
        }

        try {
            return SmtpSetting::query()->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Can WhatsApp actually deliver right now?
     *
     * What counts as "ready" differs per provider: Evolution needs a paired instance
     * row, the hosted APIs just need credentials. Asking the capability map and the
     * provider itself keeps this from becoming a growing list of provider names —
     * it used to check only for an Evolution instance, which silently disabled the
     * whole channel on any installation not running Evolution.
     */
    protected function hasUsableWhatsAppProvider(): bool
    {
        try {
            $settings = app(WhatsAppSettingsService::class);
            $provider = $settings->getSettings()['whatsapp_provider'];
            $capabilities = ProviderCapabilities::for($provider);

            if ($capabilities->requiresConnectedInstance) {
                return EvolutionInstance::query()->connected()->exists();
            }

            return WhatsAppProviderFactory::create($provider, $settings->getProviderConfig($provider))
                ->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function hasPushSubscription(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return false;
        }

        try {
            return $notifiable->pushSubscriptions()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Availability of every optional channel, for the preferences screen — so the UI
     * can disable a toggle and say why instead of letting the user enable a dead channel.
     *
     * @return array<string, array{available: bool, reason: ?string}>
     */
    public function availabilityFor(object $notifiable): array
    {
        $availability = [];

        foreach (NotificationChannel::cases() as $channel) {
            $available = $this->isAvailable($channel, $notifiable);

            $availability[$channel->value] = [
                'available' => $available,
                'reason' => $available ? null : $this->unavailableReason($channel, $notifiable),
            ];
        }

        return $availability;
    }

    protected function unavailableReason(NotificationChannel $channel, object $notifiable): ?string
    {
        return match ($channel) {
            NotificationChannel::Broadcast => 'التوصيل الفوري غير مُفعّل على السيرفر.',

            NotificationChannel::Mail => blank($notifiable->routeNotificationFor('mail'))
                ? 'لا يوجد بريد إلكتروني لهذا المستخدم.'
                : 'لا يوجد إعداد SMTP نشِط.',

            NotificationChannel::WhatsApp => blank($notifiable->routeNotificationFor('whatsapp'))
                ? 'لا يوجد رقم هاتف في ملف المستخدم.'
                : 'لا يوجد رقم واتساب متصل.',

            NotificationChannel::WebPush => 'لم يتم تسجيل هذا المتصفح لاستقبال الإشعارات.',

            NotificationChannel::Database => null,
        };
    }
}
