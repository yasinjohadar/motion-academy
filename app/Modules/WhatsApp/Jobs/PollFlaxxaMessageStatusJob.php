<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\Providers\FlaxxaApiProvider;
use App\Modules\WhatsApp\Services\WhatsAppProviderFactory;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asks Flaxxa what happened to a message.
 *
 * Every other provider pushes delivery state over a webhook. Flaxxa documents none, so
 * the only way to move a message past `sent` is to ask — and each ask costs one of the
 * 60 requests per minute the token allows. The job therefore stops as soon as the state
 * is final and gives up after a bounded number of attempts rather than following a
 * message forever.
 */
class PollFlaxxaMessageStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** States that will never change again, so polling stops. */
    private const FINAL_STATES = [
        WhatsAppMessage::STATUS_DELIVERED,
        WhatsAppMessage::STATUS_READ,
        WhatsAppMessage::STATUS_FAILED,
    ];

    public function __construct(
        public WhatsAppMessage $message,
        public int $flaxxaMessageId,
        public int $attempt = 1,
    ) {
        /*
         * Set here rather than as a `$queue` property: Illuminate\Bus\Queueable already
         * declares `public $queue` untyped, and PHP fatals on a differing redeclaration.
         */
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsAppSettingsService $settings): void
    {
        $message = $this->message->fresh();

        if (! $message || in_array($message->status, self::FINAL_STATES, true)) {
            return;
        }

        $provider = WhatsAppProviderFactory::create('flaxxa', $settings->getProviderConfig('flaxxa'));

        if (! $provider instanceof FlaxxaApiProvider || ! $provider->isConfigured()) {
            return;
        }

        try {
            $status = $provider->fetchStatus($this->flaxxaMessageId);
        } catch (WhatsAppApiException $e) {
            // A polling failure must never mark the message itself as failed — the send
            // succeeded; only our question about it did not.
            Log::channel('whatsapp')->warning('Flaxxa status poll failed', [
                'message_id' => $message->id,
                'attempt' => $this->attempt,
                'error' => $e->getMessage(),
            ]);

            $this->retryLater();

            return;
        }

        $mapped = $this->mapStatus($status['value']);

        if ($mapped === null) {
            $this->retryLater();

            return;
        }

        $message->update(array_filter([
            'status' => $mapped,
            'error' => $mapped === WhatsAppMessage::STATUS_FAILED && filled($status['error'])
                ? ['message' => $status['error']]
                : null,
        ], fn ($value) => $value !== null));

        if (! in_array($mapped, self::FINAL_STATES, true)) {
            $this->retryLater();
        }
    }

    /**
     * Backs off so a message that is simply slow does not burn the minute's quota.
     */
    protected function retryLater(): void
    {
        $max = (int) config('whatsapp.flaxxa.status_poll_max_attempts', 4);

        if ($this->attempt >= $max) {
            return;
        }

        $base = (int) config('whatsapp.flaxxa.status_poll_delay_seconds', 15);

        self::dispatch($this->message, $this->flaxxaMessageId, $this->attempt + 1)
            ->delay(now()->addSeconds($base * (2 ** $this->attempt)));
    }

    protected function mapStatus(?string $value): ?string
    {
        return match (strtolower((string) $value)) {
            'sent' => WhatsAppMessage::STATUS_SENT,
            'delivered' => WhatsAppMessage::STATUS_DELIVERED,
            'read', 'played' => WhatsAppMessage::STATUS_READ,
            'failed', 'error', 'undelivered' => WhatsAppMessage::STATUS_FAILED,
            default => null,
        };
    }
}
