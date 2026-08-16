<?php

namespace App\Modules\WhatsApp\Listeners;

use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Notifications\WhatsAppMessageReceivedNotification;
use App\Support\NotificationRecipients;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Discovered automatically by Laravel's event discovery — do not register manually.
 *
 * Inbound WhatsApp traffic is bursty, so this is rate limited: at most one notification
 * per contact per minute. Without it a chatty contact would bury every other
 * notification in the bell.
 */
class NotifyOnWhatsAppMessageReceived implements ShouldQueue
{
    public string $queue = 'whatsapp';

    public function __construct(private RateLimiter $limiter) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        $message = $event->message->loadMissing('contact');

        $key = 'whatsapp-inbound-notification:'.($message->contact_id ?? 'unknown');

        if ($this->limiter->tooManyAttempts($key, maxAttempts: 1)) {
            return;
        }

        $this->limiter->hit($key, decaySeconds: 60);

        $recipients = NotificationRecipients::forPermission('admin.whatsapp-messages.list');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new WhatsAppMessageReceivedNotification($message));
    }
}
