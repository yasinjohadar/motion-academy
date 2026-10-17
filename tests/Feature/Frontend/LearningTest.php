<?php

namespace Tests\Feature\Frontend;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use App\Models\CourseSection;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Learning\LearningProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    /** @var Collection<int, CourseLesson> in curriculum order */
    private Collection $lessons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->published()->create(['slug' => 'motion-101']);
        $this->lessons = collect();

        // Two sections of two lessons; the second section is created first but
        // ordered after, so the curriculum must follow `order`, not ids.
        $second = CourseSection::factory()->create(['course_id' => $this->course->id, 'order' => 2, 'title' => 'الوحدة الثانية']);
        $first = CourseSection::factory()->create(['course_id' => $this->course->id, 'order' => 1, 'title' => 'الوحدة الأولى']);

        foreach ([$first, $second] as $section) {
            foreach ([1, 2] as $order) {
                $this->lessons->push(CourseLesson::factory()->create([
                    'course_section_id' => $section->id,
                    'order' => $order,
                    'title' => $section->title.' — درس '.$order,
                    'duration_seconds' => 600,
                ]));
            }
        }
    }

    private function student(string $status = CourseEnrollment::STATUS_APPROVED): User
    {
        $user = User::factory()->create();
        CourseEnrollment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);

        return $user;
    }

    private function lessonUrl(CourseLesson $lesson): string
    {
        return route('learn.lesson', [$this->course->slug, $lesson->id]);
    }

    private function enrollmentOf(User $user): CourseEnrollment
    {
        return CourseEnrollment::query()->where('user_id', $user->id)->where('course_id', $this->course->id)->sole();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get($this->lessonUrl($this->lessons[0]))->assertRedirect(route('login'));
    }

    public function test_the_lesson_lists_its_links_and_files(): void
    {
        $lesson = $this->lessons[0];
        CourseLessonAttachment::factory()->create(['course_lesson_id' => $lesson->id, 'title' => 'مستودع المشروع', 'url' => 'https://github.com/example/repo', 'order' => 1]);
        CourseLessonAttachment::factory()->file()->create(['course_lesson_id' => $lesson->id, 'title' => 'ملف التمرين', 'order' => 2]);

        $this->actingAs($this->student())
            ->get($this->lessonUrl($lesson))
            ->assertOk()
            ->assertSee('مرفقات الدرس')
            ->assertSeeInOrder(['مستودع المشروع', 'ملف التمرين'])
            ->assertSee('https://github.com/example/repo', false)
            ->assertSee('download="exercise.pdf"', false);

        // A lesson without resources shows no empty section.
        $this->actingAs($this->student())
            ->get($this->lessonUrl($this->lessons[1]))
            ->assertDontSee('مرفقات الدرس');
    }

    public function test_only_approved_students_can_open_lessons(): void
    {
        foreach ([CourseEnrollment::STATUS_PENDING, CourseEnrollment::STATUS_REJECTED] as $status) {
            $this->actingAs($this->student($status))
                ->get($this->lessonUrl($this->lessons[0]))
                ->assertRedirect(route('courses.show', $this->course->slug).'#enroll')
                ->assertSessionHasErrors('enrollment');
        }

        $this->actingAs(User::factory()->create())
            ->get($this->lessonUrl($this->lessons[0]))
            ->assertRedirect(route('courses.show', $this->course->slug).'#enroll');

        $this->actingAs($this->student())
            ->get($this->lessonUrl($this->lessons[0]))
            ->assertOk()
            ->assertViewIs('frontend.student.learn')
            ->assertSee($this->lessons[0]->title)
            ->assertSee('<video', false);
    }

    public function test_course_staff_can_preview_lessons_without_enrolling(): void
    {
        Permission::findOrCreate('admin.courses.edit', 'web');
        $staff = User::factory()->create();
        $staff->givePermissionTo('admin.courses.edit');

        $this->actingAs($staff)->get($this->lessonUrl($this->lessons[0]))->assertOk();
    }

    public function test_hidden_lessons_and_lessons_of_other_courses_are_not_found(): void
    {
        $student = $this->student();
        $hidden = CourseLesson::factory()->hidden()->create(['course_section_id' => $this->lessons[0]->course_section_id]);
        $foreign = CourseLesson::factory()->create();

        $this->actingAs($student)->get($this->lessonUrl($hidden))->assertNotFound();
        $this->actingAs($student)->get($this->lessonUrl($foreign))->assertNotFound();
    }

    public function test_opening_the_course_resumes_the_last_lesson(): void
    {
        $student = $this->student();

        // First visit: the first lesson in curriculum order.
        $this->actingAs($student)->get(route('learn.course', $this->course->slug))
            ->assertRedirect($this->lessonUrl($this->lessons[0]));

        $this->actingAs($student)->get($this->lessonUrl($this->lessons[2]))->assertOk();
        $this->assertSame($this->lessons[2]->id, $this->enrollmentOf($student)->last_lesson_id);

        $this->actingAs($student)->get(route('learn.course', $this->course->slug))
            ->assertRedirect($this->lessonUrl($this->lessons[2]));
    }

    public function test_previous_and_next_follow_sections_then_lessons(): void
    {
        $this->actingAs($this->student())
            ->get($this->lessonUrl($this->lessons[1]))
            ->assertViewHas('neighbours', fn (array $n) => $n['previous']->is($this->lessons[0])
                && $n['next']->is($this->lessons[2])
                && $n['position'] === 2
                && $n['total'] === 4);
    }

    public function test_completing_lessons_updates_the_course_percentage(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->postJson(route('learn.complete', $this->lessons[0]->id), ['completed' => true])
            ->assertOk()
            ->assertJson(['completed' => true, 'percent' => 25, 'course_completed' => false]);

        $this->actingAs($student)
            ->postJson(route('learn.complete', $this->lessons[0]->id), ['completed' => false])
            ->assertJson(['completed' => false, 'percent' => 0]);

        foreach ($this->lessons as $lesson) {
            $response = $this->actingAs($student)->postJson(route('learn.complete', $lesson->id));
        }

        $response->assertJson(['percent' => 100, 'course_completed' => true]);
        $this->assertNotNull($this->enrollmentOf($student)->completed_at);

        // Undoing one lesson re-opens the course.
        $this->actingAs($student)->postJson(route('learn.complete', $this->lessons[3]->id), ['completed' => false]);
        $this->assertNull($this->enrollmentOf($student)->completed_at);
        $this->assertSame(75, $this->enrollmentOf($student)->progress_percent);
    }

    public function test_complete_works_without_javascript(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->from($this->lessonUrl($this->lessons[0]))
            ->post(route('learn.complete', $this->lessons[0]->id), ['completed' => '1'])
            ->assertRedirect($this->lessonUrl($this->lessons[0]));

        $this->assertSame(25, $this->enrollmentOf($student)->progress_percent);
    }

    public function test_watching_most_of_a_video_completes_it(): void
    {
        $student = $this->student();
        $lesson = $this->lessons[0];

        $this->actingAs($student)
            ->postJson(route('learn.position', $lesson->id), ['seconds' => 120])
            ->assertJson(['completed' => false]);
        $this->assertSame(120, LessonProgress::query()->where('user_id', $student->id)->sole()->position_seconds);

        // 540 of 600 seconds is 90%.
        $this->actingAs($student)
            ->postJson(route('learn.position', $lesson->id), ['seconds' => 540])
            ->assertJson(['completed' => true, 'percent' => 25]);

        $this->actingAs($student)->get($this->lessonUrl($lesson))->assertViewHas('position', 540);
    }

    public function test_reported_duration_is_used_when_the_lesson_has_none(): void
    {
        $student = $this->student();
        $lesson = $this->lessons[0];
        $lesson->update(['duration_seconds' => 0]);

        $this->actingAs($student)
            ->postJson(route('learn.position', $lesson->id), ['seconds' => 95, 'duration' => 100])
            ->assertJson(['completed' => true]);
    }

    public function test_progress_calls_are_refused_for_students_outside_the_course(): void
    {
        $outsider = $this->student(CourseEnrollment::STATUS_PENDING);

        $this->actingAs($outsider)->postJson(route('learn.complete', $this->lessons[0]->id))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('learn.position', $this->lessons[0]->id), ['seconds' => 10])->assertForbidden();
        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_hidden_lessons_do_not_count_toward_progress(): void
    {
        $student = $this->student();
        CourseLesson::factory()->hidden()->create(['course_section_id' => $this->lessons[0]->course_section_id]);

        foreach ($this->lessons as $lesson) {
            app(LearningProgress::class)->markComplete($student, $lesson);
        }

        $this->assertSame(100, $this->enrollmentOf($student)->progress_percent);
    }

    public function test_old_lesson_address_redirects_to_the_player(): void
    {
        $this->get(route('lessons.show', $this->lessons[1]->id))
            ->assertStatus(301)
            ->assertRedirect($this->lessonUrl($this->lessons[1]));
    }

    public function test_course_page_shows_progress_and_lesson_links_to_enrolled_students(): void
    {
        $student = $this->student();
        app(LearningProgress::class)->markComplete($student, $this->lessons[0]);

        $this->actingAs($student)
            ->get(route('courses.show', $this->course->slug))
            ->assertSee('تابع التعلّم')
            ->assertSee($this->lessonUrl($this->lessons[1]), false);

        auth()->logout();

        $this->get(route('courses.show', $this->course->slug))
            ->assertDontSee($this->lessonUrl($this->lessons[1]), false);
    }
}
