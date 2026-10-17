<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            // Copied from the lesson, as LearningProgress does.
            'course_id' => fn (array $attributes) => CourseLesson::query()->findOrFail($attributes['course_lesson_id'])->course_id,
            'position_seconds' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
