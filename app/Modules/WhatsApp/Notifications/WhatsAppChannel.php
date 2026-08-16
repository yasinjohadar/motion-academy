<?php

namespace App\Modules\WhatsApp\Notifications;

use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Services\ProviderCapabilities;
use App\Modules\WhatsApp\Services\SendWhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppProviderFactory;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use App\Notifications\BaseNotification;
use App\Support\Notifications\ReportsChannelAvailability;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Delivers notifications over WhatsApp via the existing Evolution integration.
 *
 * All the hard parts — number normalisation, contact creation, the outbound message
 * row, instance rotation and failover — are already handled by
 * {@see SendWhatsAppMessage::sendText()}, which queues a
 * SendWhatsAppMessageJob. Queueing there is deliberate: a WhatsApp API failure must
 * not take the rest of the notification's channels down with it.
 */
class WhatsAppChannel implements ReportsChannelAvailability
{
    public function __construct(private SendWhatsAppMessage $sendService) {}

    /**
     * Whether WhatsApp can deliver to this notifiable right now.
     *
     * This lives here, not in NotificationChannelResolver: what counts as "ready"
     * differs per provider — Evolution needs a paired instance row, the hosted APIs
     * only need credentials — and that is knowledge belonging to the WhatsApp module.
     * The core resolver used to import four classes from here to answer it.
     */
    public function isAvailableFor(object $notifiable): bool
    {
        if (blank($notifiable->routeNotificationFor('whatsapp'))) {
            return false;
        }

        try {
            $settings = app(WhatsAppSettingsService::class);
            $provider = $settings->getSettings()['whatsapp_provider'];

            if (ProviderCapabilities::for($provider)->requiresConnectedInstance) {
                return EvolutionInstance::query()->connected()->exists();
            }

            return WhatsAppProviderFactory::create($provider, $settings->getProviderConfig($provider))
                ->isConfigured();
        } catch (\Throwable) {
            // A misconfigured provider is an unavailable channel, never an exception
            // that takes the preferences screen down with it.
            return false;
        }
    }

    public function unavailableReasonFor(object $notifiable): ?string
    {
        if (blank($notifiable->routeNotificationFor('whatsapp'))) {
            return 'لا يوجد رقم هاتف في ملف المستخدم.';
        }

        return 'لا يوجد مزوّد واتساب مُهيّأ أو رقم متصل.';
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof BaseNotification) {
            return;
        }

        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (blank($to)) {
            return;
        }

        // The notification states its intent as plain strings; translating those into
        // this module's vocabulary is this class's job, not the core's.
        $options = $notification->channelOptions('whatsapp', $notifiable);

        $purpose = WhatsAppPurpose::tryFrom((string) ($options['purpose'] ?? ''))
            ?? WhatsAppPurpose::SystemNotification;

        try {
            $this->sendService->sendText(
                (string) $to,
                $notification->toWhatsApp($notifiable),
                purpose: $purpose,
                templateParameters: array_values((array) ($options['template_parameters'] ?? [])),
            );
        } catch (\Throwable $e) {
            // A dead WhatsApp instance must not fail the whole notification — the
            // database record has already been written by this point.
            Log::channel('whatsapp')->warning('Failed to queue WhatsApp notification', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
