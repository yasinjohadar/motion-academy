<?php

namespace Database\Factories;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    protected $model = WhatsAppMessage::class;

    public function definition(): array
    {
        return [
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => WhatsAppContact::factory(),
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => fake()->sentence(),
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'provider' => null,
            'meta_message_id' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);
    }

    /**
     * Sent through a specific provider — what the status poller queries on.
     */
    public function sentVia(string $provider, ?string $metaMessageId = null): static
    {
        return $this->state(fn (): array => [
            'provider' => $provider,
            'status' => WhatsAppMessage::STATUS_SENT,
            'meta_message_id' => $metaMessageId ?? 'wamid.'.fake()->uuid(),
        ]);
    }

    public function failed(string $reason = 'فشل الإرسال'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error' => ['message' => $reason],
        ]);
    }
}
