<?php

namespace Database\Factories;

use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppBroadcastRecipient>
 */
class WhatsAppBroadcastRecipientFactory extends Factory
{
    protected $model = WhatsAppBroadcastRecipient::class;

    public function definition(): array
    {
        return [
            // The column is `broadcast_id`, not `whatsapp_broadcast_id`.
            'broadcast_id' => WhatsAppBroadcast::factory(),
            'phone' => '9665'.fake()->numerify('########'),
            'status' => WhatsAppBroadcastRecipient::STATUS_PENDING,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsAppBroadcastRecipient::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $reason = 'رقم غير صالح'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsAppBroadcastRecipient::STATUS_FAILED,
            'error_message' => $reason,
        ]);
    }
}
