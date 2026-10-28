<?php

namespace Database\Factories;

use App\Enums\HeroSlideVisual;
use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'badge_text' => fake()->words(2, true),
            'badge_icon' => 'fas fa-play',
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'primary_button_label' => 'تصفح الدورات',
            'primary_button_url' => '/courses',
            'primary_button_icon' => 'fas fa-arrow-left',
            'bullet_label' => fake()->word(),
            'visual' => HeroSlideVisual::None,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withImage(): static
    {
        return $this->state(fn () => [
            'visual' => HeroSlideVisual::Image,
            'image' => 'https://example.com/hero.jpg',
            'image_alt' => 'صورة الشريحة',
        ]);
    }
}
