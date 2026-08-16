<?php

namespace App\Modules\WhatsApp\Database\Factories;

use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppBroadcast>
 */
class WhatsAppBroadcastFactory extends Factory
{
    protected $model = WhatsAppBroadcast::class;

    public function definition(): array
    {
        return [
            'message_template' => fake()->sentence(),
            'send_type' => WhatsAppBroadcast::TYPE_TEXT,
            'status' => WhatsAppBroadcast::STATUS_PENDING,
            'total_recipients' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            // NOT NULL in the schema, so it cannot be left to chance.
            'created_by' => User::factory(),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => ['status' => WhatsAppBroadcast::STATUS_PROCESSING]);
    }
}
