<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Modules\WhatsApp\Models\WhatsAppBroadcast;
use App\Modules\WhatsApp\Models\WhatsAppBroadcastRecipient;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Notifications\WhatsAppCampaignCompleted;
use App\Modules\WhatsApp\Services\BroadcastWhatsAppMessage;
use App\Modules\WhatsApp\Services\SendWhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use App\Support\NotificationRecipients;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BroadcastWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(
        public WhatsAppBroadcast $broadcast,
        public string $phone,
        public string $message,
        public ?int $userId = null,
        public ?int $delaySeconds = null,
        public int $messageIndex = 0,
    ) {
        if ($this->delaySeconds === null) {
            $this->delaySeconds = app(WhatsAppSettingsService::class)->calculateDelay();
        }

        if ($this->delaySeconds > 0) {
            $this->delay($this->delaySeconds);
        }
    }

    public function handle(
        SendWhatsAppMessage $sendService,
        BroadcastWhatsAppMessage $broadcastService,
    ): void {
        $user = $this->userId ? User::query()->find($this->userId) : null;
        $body = $broadcastService->replacePlaceholders($this->message, $user, $this->phone);

        try {
            if ($this->broadcast->status === WhatsAppBroadcast::STATUS_PENDING) {
                $this->broadcast->update(['status' => WhatsAppBroadcast::STATUS_PROCESSING]);
            }

            // Campaigns are business-initiated: the routing matrix decides the provider,
            // and on the official providers this is what selects the approved template.
            $outboundMessage = $sendService->sendTextSync(
                $this->phone,
                $body,
                purpose: WhatsAppPurpose::Campaign,
            );

            if ($outboundMessage->status !== WhatsAppMessage::STATUS_SENT) {
                throw new Exception(
                    data_get($outboundMessage->error, 'message', 'فشل إرسال الرسالة للمستلم.')
                );
            }

            $this->markRecipient(WhatsAppBroadcastRecipient::STATUS_SENT);
            $this->broadcast->increment('sent_count');
        } catch (Exception $e) {
            $this->markRecipient(WhatsAppBroadcastRecipient::STATUS_FAILED, $e->getMessage());
            $this->broadcast->increment('failed_count');

            Log::channel('whatsapp')->error('Failed to send broadcast message', [
                'broadcast_id' => $this->broadcast->id,
                'phone' => $this->phone,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->broadcast->refresh();
            $totalProcessed = $this->broadcast->sent_count + $this->broadcast->failed_count;
            if ($totalProcessed >= $this->broadcast->total_recipients) {
                $status = $this->broadcast->failed_count === $this->broadcast->total_recipients
                    ? WhatsAppBroadcast::STATUS_FAILED
                    : WhatsAppBroadcast::STATUS_COMPLETED;

                $alreadyFinished = in_array($this->broadcast->status, [
                    WhatsAppBroadcast::STATUS_COMPLETED,
                    WhatsAppBroadcast::STATUS_FAILED,
                ], true);

                $this->broadcast->update(['status' => $status]);

                // Several recipient jobs can reach this block concurrently; only the
                // one that actually transitions the campaign notifies.
                if (! $alreadyFinished) {
                    $this->notifyCampaignFinished();
                }
            }
        }
    }

    private function notifyCampaignFinished(): void
    {
        $recipients = NotificationRecipients::forPermissionIncluding(
            'admin.whatsapp-campaigns.list',
            $this->broadcast->creator
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new WhatsAppCampaignCompleted($this->broadcast));
    }

    private function markRecipient(string $status, ?string $error = null): void
    {
        $query = WhatsAppBroadcastRecipient::query()
            ->where('broadcast_id', $this->broadcast->id)
            ->where('phone', $this->phone);

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        $recipient = $query->first();
        if (! $recipient) {
            return;
        }

        $recipient->update([
            'status' => $status,
            'error_message' => $error,
            'sent_at' => $status === WhatsAppBroadcastRecipient::STATUS_SENT ? now() : null,
        ]);
    }
}
