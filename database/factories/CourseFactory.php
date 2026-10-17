<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'course_category_id' => CourseCategory::factory(),
            'instructor_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'subtitle' => fake()->sentence(),
            'short_description' => fake()->sentence(12),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'level' => CourseLevel::Beginner,
            'language' => 'ar',
            'status' => CourseStatus::Draft,
            'is_featured' => false,
            'is_free' => false,
            'price' => 49.00,
            'currency' => 'USD',
            'learning_outcomes' => [fake()->sentence(), fake()->sentence()],
            'requirements' => [fake()->sentence()],
            'target_audience' => [fake()->sentence()],
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => CourseStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['is_free' => true, 'price' => 0, 'sale_price' => null]);
    }

    public function onSale(): static
    {
        return $this->state(fn () => [
            'is_free' => false,
            'price' => 100.00,
            'sale_price' => 60.00,
            'sale_ends_at' => now()->addWeek(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
