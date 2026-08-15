<?php

namespace App\Jobs;

use App\Events\WhatsAppMessageReceived;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\CustomerServiceWindow;
use App\Services\WhatsApp\Evolution\EvolutionWebhookParser;
use App\Services\WhatsApp\Meta\MetaWebhookParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public WhatsAppWebhookEvent $webhookEvent
    ) {
        /*
         * Set here rather than as a `$queue` property: Illuminate\Bus\Queueable already
         * declares `public $queue` with no type and no default, and PHP fatals on a
         * redeclaration that differs. The previous `public string $queue = 'whatsapp'`
         * made this class impossible to load at all.
         */
        $this->onQueue('whatsapp');
    }

    public function handle(EvolutionWebhookParser $evolutionParser, MetaWebhookParser $metaParser): void
    {
        try {
            $payload = $this->webhookEvent->payload;

            // Both parsers emit the same DTOs, so everything below stays shared.
            $parsed = $this->webhookEvent->provider === 'meta'
                ? $metaParser->parse($payload)
                : $evolutionParser->parse($payload);

            foreach ($parsed['messages'] as $messageDTO) {
                $this->processInboundMessage($messageDTO);
            }

            foreach ($parsed['statuses'] as $statusDTO) {
                $this->processStatusUpdate($statusDTO);
            }

            $this->webhookEvent->markAsProcessed();
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing webhook event', [
                'event_id' => $this->webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function processInboundMessage($messageDTO): void
    {
        $contact = WhatsAppContact::findOrCreateByWaId($messageDTO->from);
        $contact->updateLastSeen();

        // An inbound message opens Meta's 24-hour customer service window.
        app(CustomerServiceWindow::class)->markInbound($contact);

        $instanceName = (string) ($this->webhookEvent->payload['instance'] ?? '');
        $payload = $messageDTO->metadata ?? [];
        if ($instanceName !== '') {
            $payload['evolution_instance_name'] = $instanceName;
        }

        $message = WhatsAppMessage::firstOrCreate(
            [
                'direction' => WhatsAppMessage::DIRECTION_INBOUND,
                'meta_message_id' => $messageDTO->messageId,
            ],
            [
                'contact_id' => $contact->id,
                'type' => $messageDTO->type,
                'body' => $messageDTO->textBody,
                'status' => WhatsAppMessage::STATUS_DELIVERED,
                'payload' => $payload,
            ]
        );

        // Only on first insert — webhooks are retried and firstOrCreate is idempotent,
        // but the event must not be.
        if ($message->wasRecentlyCreated) {
            WhatsAppMessageReceived::dispatch($message);
        }
    }

    protected function processStatusUpdate($statusDTO): void
    {
        $message = WhatsAppMessage::query()
            ->where('meta_message_id', $statusDTO->messageId)
            ->first();

        if (! $message) {
            return;
        }

        $status = match (strtolower((string) $statusDTO->status)) {
            'delivered' => WhatsAppMessage::STATUS_DELIVERED,
            'read', 'played' => WhatsAppMessage::STATUS_READ,
            'failed' => WhatsAppMessage::STATUS_FAILED,
            default => WhatsAppMessage::STATUS_SENT,
        };

        $message->update(['status' => $status]);
    }
}
