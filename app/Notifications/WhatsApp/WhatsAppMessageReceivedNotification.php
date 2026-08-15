<?php

namespace App\Notifications\WhatsApp;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\WhatsAppMessage;
use App\Notifications\BaseNotification;
use Illuminate\Support\Str;

class WhatsAppMessageReceivedNotification extends BaseNotification
{
    public function __construct(public WhatsAppMessage $message)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::WhatsApp;
    }

    /**
     * Never deliver an inbound-WhatsApp alert back over WhatsApp — a reply to that
     * message would trigger another one, and so on. Mail and Web Push are also
     * excluded: inbound traffic is high volume and belongs in-app only.
     *
     * @return array<int, NotificationChannel>
     */
    public function supportedChannels(): array
    {
        return [NotificationChannel::Database, NotificationChannel::Broadcast];
    }

    public function title(object $notifiable): string
    {
        $name = $this->message->contact?->name ?: $this->message->contact?->wa_id;

        return 'رسالة واتساب جديدة'.($name ? " من {$name}" : '');
    }

    public function message(object $notifiable): string
    {
        $body = trim((string) $this->message->body);

        if ($body === '') {
            return 'وصلت رسالة من نوع '.$this->message->type.'.';
        }

        return Str::limit($body, 140);
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.whatsapp-messages.index');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'wa_id' => $this->message->contact?->wa_id,
        ];
    }
}
