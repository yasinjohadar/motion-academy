<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Learning\LearningProgress;
use App\Services\Observability\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithHistory(): User
    {
        $student = User::factory()->student()->create([
            'name' => 'سارة أحمد',
            'name_ar' => 'سارة أحمد',
            'email' => 'sara@example.com',
            'phone_e164' => '963991234567',
        ]);

        $course = Course::factory()->published()->create(['title' => 'أساسيات التحريك']);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = CourseLesson::factory()->count(4)->create(['course_section_id' => $section->id, 'duration_seconds' => 600]);
        $enrollment = CourseEnrollment::factory()->approved()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        app(LearningProgress::class)->markComplete($student, $lessons[0]);
        app(LearningProgress::class)->touch($enrollment->fresh(), $lessons[1]);
        app(LearningProgress::class)->recordPosition($student, $lessons[1], 120);

        CourseEnrollment::factory()->create(['user_id' => $student->id, 'message' => 'أريد الانضمام']);
        CourseEnrollment::factory()->rejected()->create(['user_id' => $student->id]);
        NewsletterSubscriber::factory()->create(['email' => 'sara@example.com']);

        app(SecurityLogger::class)->record('login', 'تسجيل دخول ناجح', $student);

        return $student;
    }

    public function test_the_page_shows_everything_about_a_student(): void
    {
        $student = $this->studentWithHistory();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Show')
                ->where('user.email', 'sara@example.com')
                ->where('user.phone', '+963 991 234 567')
                ->where('user.account_type', 'student')
                ->where('stats.courses', 1)
                ->where('stats.pending', 1)
                ->where('stats.rejected', 1)
                ->where('stats.lessons', 1)
                ->where('stats.minutes', 10)
                ->has('courses', 1)
                ->where('courses.0.title', 'أساسيات التحريك')
                ->where('courses.0.progress', 25)
                ->where('courses.0.done', 1)
                ->where('courses.0.total', 4)
                ->has('requests', 3)
                ->has('recentLessons', 2)
                ->where('newsletter.status', 'subscribed')
                ->where('security.0.event', 'login'));
    }

    public function test_security_events_need_their_own_permission(): void
    {
        $student = $this->studentWithHistory();
        $viewer = $this->panelUserWithoutPermissions();
        $viewer->givePermissionTo(Permission::findOrCreate('admin.users.list', 'web'));

        $this->actingAs($viewer)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('security', null)->has('courses', 1));
    }

    public function test_a_course_deleted_later_still_shows_in_the_history(): void
    {
        $student = $this->studentWithHistory();
        Course::query()->where('title', 'أساسيات التحريك')->sole()->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('courses.0.deleted', true));
    }

    public function test_access_follows_the_users_list_permission(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->panelUserWithoutPermissions())
            ->get(route('admin.users.show', $student))
            ->assertForbidden();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.users.show', $student))
            ->assertRedirect(route('student.dashboard'));
    }
}
