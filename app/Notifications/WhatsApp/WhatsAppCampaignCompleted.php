<?php

namespace App\Notifications\WhatsApp;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\WhatsAppBroadcast;
use App\Notifications\BaseNotification;
use Illuminate\Support\Str;

class WhatsAppCampaignCompleted extends BaseNotification
{
    public function __construct(public WhatsAppBroadcast $broadcast)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::WhatsApp;
    }

    public function level(): NotificationLevel
    {
        return $this->broadcast->status === WhatsAppBroadcast::STATUS_FAILED
            ? NotificationLevel::Danger
            : NotificationLevel::Success;
    }

    public function title(object $notifiable): string
    {
        return $this->broadcast->status === WhatsAppBroadcast::STATUS_FAILED
            ? 'فشلت حملة واتساب'
            : 'اكتملت حملة واتساب';
    }

    public function message(object $notifiable): string
    {
        return sprintf(
            'الحملة «%s»: تم إرسال %d من %d، وفشل %d.',
            Str::limit((string) $this->broadcast->message_template, 40) ?: '—',
            (int) $this->broadcast->sent_count,
            (int) $this->broadcast->total_recipients,
            (int) $this->broadcast->failed_count,
        );
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.whatsapp-campaigns.show', $this->broadcast->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'sent_count' => (int) $this->broadcast->sent_count,
            'failed_count' => (int) $this->broadcast->failed_count,
        ];
    }
}
