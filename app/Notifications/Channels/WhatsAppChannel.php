<?php

namespace App\Notifications\Channels;

use App\Notifications\BaseNotification;
use App\Services\WhatsApp\SendWhatsAppMessage;
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
class WhatsAppChannel
{
    public function __construct(private SendWhatsAppMessage $sendService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof BaseNotification) {
            return;
        }

        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (blank($to)) {
            return;
        }

        try {
            $this->sendService->sendText(
                (string) $to,
                $notification->toWhatsApp($notifiable),
                purpose: $notification->whatsAppPurpose(),
                templateParameters: $notification->whatsAppTemplateParameters($notifiable),
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
