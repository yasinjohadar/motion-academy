<?php

namespace Database\Factories;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_section_id' => CourseSection::factory(),
            'title' => fake()->sentence(4),
            'type' => LessonType::Video,
            'video_source' => VideoSource::Url,
            'video' => 'https://cdn.example.com/videos/'.fake()->uuid().'.mp4',
            'duration_seconds' => fake()->numberBetween(60, 1800),
            'is_preview' => false,
            'is_published' => true,
            'order' => 0,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn () => [
            'type' => LessonType::Video,
            'video_source' => VideoSource::Youtube,
            'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function text(): static
    {
        return $this->state(fn () => [
            'type' => LessonType::Text,
            'video_source' => null,
            'video' => null,
            'content' => '<p>'.fake()->paragraphs(2, true).'</p>',
        ]);
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'type' => LessonType::File,
            'video_source' => null,
            'video' => null,
            'attachment' => 'https://cdn.example.com/files/'.fake()->uuid().'.pdf',
            'duration_seconds' => 0,
        ]);
    }

    public function preview(): static
    {
        return $this->state(fn () => ['is_preview' => true]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
