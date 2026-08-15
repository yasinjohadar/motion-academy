<?php

namespace App\Jobs;

use App\Exceptions\WhatsAppApiException;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppOutboundSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public WhatsAppMessage $message,
        public array $messageData = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppOutboundSendService $outboundSend): void
    {
        try {
            $this->message->loadMissing('contact');
            $outboundSend->send($this->message, $this->messageData);

            $this->message->refresh();

            Log::channel('whatsapp')->info('WhatsApp message sent via job', [
                'message_id' => $this->message->id,
                'meta_message_id' => $this->message->meta_message_id,
                'to' => $this->message->contact?->wa_id,
            ]);
        } catch (WhatsAppApiException $e) {
            // Update message with error
            $this->message->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'details' => $e->getDetails(),
                ],
            ]);

            Log::channel('whatsapp')->error('Failed to send WhatsApp message', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'details' => $e->getDetails(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            $safeMessage = $this->toSingleLineError($e->getMessage());

            // Update message with error
            $this->message->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error' => [
                    'message' => $safeMessage,
                ],
            ]);

            Log::channel('whatsapp')->error('Exception sending WhatsApp message', [
                'message_id' => $this->message->id,
                'error' => $safeMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function toSingleLineError(string $message): string
    {
        $parts = preg_split('/\R+/', trim($message));

        return (string) ($parts[0] ?? 'حدث خطأ غير متوقع أثناء الإرسال.');
    }
}
