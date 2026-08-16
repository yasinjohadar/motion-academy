<?php

namespace App\Modules\WhatsApp\Database\Factories;

use App\Modules\WhatsApp\Models\EvolutionInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvolutionInstance>
 */
class EvolutionInstanceFactory extends Factory
{
    protected $model = EvolutionInstance::class;

    public function definition(): array
    {
        return [
            'instance_name' => 'instance-'.fake()->unique()->numerify('####'),
            'label' => fake()->words(2, true),
            // Default is 'close'; use connected() when the test needs a usable instance.
            'connection_status' => 'close',
            'rotation_enabled' => true,
            'evolution_base_url' => 'https://evo.example.com',
        ];
    }

    /**
     * `connected` means connection_status = 'open' — that is what scopeConnected()
     * and the rotation pool actually query on.
     */
    public function connected(): static
    {
        return $this->state(fn (): array => [
            'connection_status' => 'open',
            'connected_at' => now(),
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
