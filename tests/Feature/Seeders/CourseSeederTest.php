<?php

namespace Tests\Feature\Seeders;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_published_courses_with_curriculum_and_a_teacher(): void
    {
        $this->seed(CourseSeeder::class);

        $this->assertSame(6, Course::query()->count());
        $this->assertSame(6, Course::query()->where('status', CourseStatus::Published)->count());
        $this->assertSame(2, Course::query()->where('is_free', true)->count());

        $course = Course::query()->where('slug', 'تطوير-مواقع-الويب-من-الصفر')->firstOrFail();
        $this->assertSame(3, $course->sections()->count());
        $this->assertSame(10, $course->lessons_count);
        $this->assertTrue($course->isOnSale());
        $this->assertSame('تطوير-الويب', $course->category->slug);
        $this->assertTrue(User::query()->where('email', 'teacher@example.com')->firstOrFail()->hasRole('student'));
        $this->assertSame(6, CourseLesson::query()->where('is_preview', true)->count());
    }

    public function test_stats_are_correct_even_without_model_events(): void
    {
        Course::withoutEvents(fn () => CourseLesson::withoutEvents(fn () => $this->seed(CourseSeeder::class)));

        $course = Course::query()->where('slug', 'تطوير-مواقع-الويب-من-الصفر')->firstOrFail();
        $this->assertSame(10, $course->lessons_count);
        $this->assertNotNull($course->published_at);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(CourseSeeder::class);
        $this->seed(CourseSeeder::class);

        $this->assertSame(6, Course::query()->count());
        $this->assertSame(60, CourseLesson::query()->count());
    }
}
