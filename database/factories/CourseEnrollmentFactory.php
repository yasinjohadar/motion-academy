<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEnrollment>
 */
class CourseEnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->published(),
            'user_id' => User::factory(),
            'status' => CourseEnrollment::STATUS_PENDING,
            'source' => CourseEnrollment::SOURCE_REQUEST,
            'message' => fake()->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => CourseEnrollment::STATUS_APPROVED,
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CourseEnrollment::STATUS_REJECTED,
            'decided_at' => now(),
            'admin_note' => 'الدورة مكتملة حالياً.',
        ]);
    }
}
