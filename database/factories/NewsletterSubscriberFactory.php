<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->optional()->name(),
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'source' => fake()->randomElement(['footer', 'home', 'blog-detail']),
            'subscribed_at' => now()->subDays(fake()->numberBetween(0, 60)),
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now()->subDay(),
        ]);
    }
}
