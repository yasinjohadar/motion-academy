<?php

namespace Database\Factories;

use App\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppContact>
 */
class WhatsAppContactFactory extends Factory
{
    protected $model = WhatsAppContact::class;

    public function definition(): array
    {
        return [
            // `wa_id` is unique, and tests routinely need several contacts at once.
            'wa_id' => '9665'.fake()->unique()->numerify('########'),
            'name' => fake()->firstName(),
            'last_seen_at' => now(),
            'last_inbound_at' => null,
        ];
    }

    /**
     * The 24-hour customer service window is open — free text is allowed on the
     * official providers. See CustomerServiceWindow.
     */
    public function windowOpen(): static
    {
        return $this->state(fn (): array => ['last_inbound_at' => now()->subHour()]);
    }

    /**
     * The contact wrote once, long enough ago that only a template will be accepted.
     */
    public function windowClosed(): static
    {
        return $this->state(fn (): array => ['last_inbound_at' => now()->subHours(25)]);
    }
}
