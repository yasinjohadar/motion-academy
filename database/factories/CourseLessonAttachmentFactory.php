<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLessonAttachment>
 */
class CourseLessonAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_lesson_id' => CourseLesson::factory(),
            'type' => CourseLessonAttachment::TYPE_LINK,
            'title' => fake()->words(3, true),
            'url' => 'https://example.com/resources/'.fake()->slug(),
            'order' => 0,
        ];
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'type' => CourseLessonAttachment::TYPE_FILE,
            'url' => null,
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => 'exercise.pdf',
            'size' => 245760,
        ]);
    }
}
