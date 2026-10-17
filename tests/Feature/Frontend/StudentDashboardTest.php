<?php

namespace Tests\Feature\Frontend;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use App\Notifications\Courses\EnrollmentDecided;
use App\Services\Learning\LearningProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $title, ?string $url = null, bool $read = false): AppNotification
    {
        return AppNotification::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'type' => EnrollmentDecided::class,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'category' => 'courses',
            'level' => 'success',
            'action_url' => $url,
            'data' => ['title' => $title, 'message' => 'تفاصيل '.$title],
            'read_at' => $read ? now() : null,
        ]);
    }

    /**
     * An approved enrollment in a course with $lessons published lessons.
     */
    private function enrolledCourse(User $user, int $lessons = 4, string $title = 'دورة التحريك'): CourseEnrollment
    {
        $course = Course::factory()->published()->create(['title' => $title]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->count($lessons)->create(['course_section_id' => $section->id, 'duration_seconds' => 600]);

        return CourseEnrollment::factory()->approved()->create(['course_id' => $course->id, 'user_id' => $user->id]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['student.dashboard', 'student.courses', 'student.requests', 'student.notifications', 'student.profile'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_overview_shows_the_course_to_continue_and_real_numbers(): void
    {
        $student = User::factory()->create(['name' => 'سارة أحمد', 'name_ar' => 'سارة أحمد']);
        $enrollment = $this->enrolledCourse($student);
        $lessons = $enrollment->course->lessons()->orderBy('id')->get();
        app(LearningProgress::class)->markComplete($student, $lessons[0]);
        app(LearningProgress::class)->touch($enrollment->fresh(), $lessons[1]);
        CourseEnrollment::factory()->create(['user_id' => $student->id]); // pending
        $this->notify($student, 'تم قبول طلبك');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('سارة')
            ->assertSee('تابع من حيث توقفت')
            ->assertSee('دورة التحريك')
            ->assertSee($lessons[1]->title)
            ->assertSee('تم قبول طلبك')
            ->assertSee('طلبات بانتظار الموافقة')
            ->assertViewHas('stats', fn (array $stats) => $stats === ['active' => 1, 'completed' => 0, 'lessons' => 1, 'minutes' => 10]);
    }

    public function test_a_new_student_gets_suggestions_instead_of_an_empty_page(): void
    {
        Course::factory()->published()->create(['title' => 'دورة مقترحة']);

        $this->actingAs(User::factory()->create())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('لم تبدأ أي دورة بعد')
            ->assertSee('دورة مقترحة');
    }

    public function test_courses_are_split_into_in_progress_and_completed(): void
    {
        $student = User::factory()->create();
        $this->enrolledCourse($student, 2, 'دورة جارية');
        $done = $this->enrolledCourse($student, 1, 'دورة منتهية');
        app(LearningProgress::class)->markComplete($student, $done->course->lessons()->sole());

        $this->actingAs($student)->get(route('student.courses'))
            ->assertOk()
            ->assertSee('دورة جارية')
            ->assertDontSee('دورة منتهية')
            ->assertViewHas('counts', ['active' => 1, 'completed' => 1]);

        $this->actingAs($student)->get(route('student.courses', ['tab' => 'completed']))
            ->assertSee('دورة منتهية')
            ->assertSee('راجع الدورة')
            ->assertDontSee('دورة جارية');
    }

    public function test_requests_page_shows_every_status_with_its_action(): void
    {
        $student = User::factory()->create();
        $approved = CourseEnrollment::factory()->approved()->create(['user_id' => $student->id]);
        $pending = CourseEnrollment::factory()->create(['user_id' => $student->id]);
        $rejected = CourseEnrollment::factory()->rejected()->create(['user_id' => $student->id]);

        $this->actingAs($student)->get(route('student.requests'))
            ->assertOk()
            ->assertSee($approved->course->title)
            ->assertSee(route('learn.course', $approved->course->slug), false)
            ->assertSee($pending->course->title)
            ->assertSee('سحب الطلب')
            ->assertSee($rejected->course->title)
            ->assertSee('الدورة مكتملة حالياً.')
            ->assertSee('أعد الطلب');
    }

    public function test_notifications_can_be_filtered_and_marked_read(): void
    {
        $student = User::factory()->create();
        $unread = $this->notify($student, 'إشعار جديد', route('student.courses'));
        $this->notify($student, 'إشعار قديم', null, read: true);

        $this->actingAs($student)->get(route('student.notifications', ['state' => 'unread']))
            ->assertOk()
            ->assertSee('إشعار جديد')
            ->assertDontSee('إشعار قديم');

        // Opening a notification marks it read and follows its link.
        $this->actingAs($student)
            ->post(route('student.notifications.read', $unread->id), ['open' => 1])
            ->assertRedirect(route('student.courses'));
        $this->assertNotNull($unread->fresh()->read_at);

        $this->notify($student, 'ثالث');
        $this->actingAs($student)->post(route('student.notifications.read-all'))->assertSessionHas('student_success');
        $this->assertSame(0, AppNotification::query()->forUser($student)->whereNull('read_at')->count());
    }

    public function test_a_student_cannot_touch_someone_elses_notification(): void
    {
        $other = $this->notify(User::factory()->create(), 'ليس لك');

        $this->actingAs(User::factory()->create())
            ->post(route('student.notifications.read', $other->id))
            ->assertNotFound();

        $this->assertNull($other->fresh()->read_at);
    }

    public function test_profile_updates_name_email_and_phone(): void
    {
        $student = User::factory()->create(['phone_e164' => '963991234567', 'phone_verified_at' => now()]);

        $this->actingAs($student)->get(route('student.profile'))
            ->assertOk()
            ->assertSee('991234567');

        $this->actingAs($student)
            ->patch(route('student.profile.update'), [
                'name' => 'اسم جديد',
                'email' => 'new@example.com',
                'country_code' => '963',
                'phone' => '0944 111 222',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('student.profile'));

        $student->refresh();
        $this->assertSame('اسم جديد', $student->name);
        $this->assertSame('new@example.com', $student->email);
        $this->assertNull($student->email_verified_at);
        $this->assertSame('963944111222', $student->phone_e164);
        $this->assertNull($student->phone_verified_at);
    }

    public function test_profile_rejects_a_phone_used_by_another_account(): void
    {
        User::factory()->create(['phone_e164' => '963944111222']);
        $student = User::factory()->create();

        $this->actingAs($student)
            ->patch(route('student.profile.update'), [
                'name' => $student->name,
                'email' => $student->email,
                'country_code' => '963',
                'phone' => '944111222',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_password_can_be_changed_from_the_profile_page(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->from(route('student.profile'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'New-secret-123',
                'password_confirmation' => 'New-secret-123',
            ])
            ->assertRedirect(route('student.profile'))
            ->assertSessionHas('status', 'password-updated');

        $this->actingAs($student)->get(route('student.profile'))->assertSee('تم تغيير كلمة المرور');
    }

    public function test_navbar_offers_the_account_menu_with_unread_count(): void
    {
        $student = User::factory()->create();
        $this->notify($student, 'واحد');
        $this->notify($student, 'اثنان');

        $this->actingAs($student)->get(route('home'))
            ->assertOk()
            ->assertSee('data-nav-account', false)
            ->assertSee(route('student.dashboard'), false)
            ->assertSee('<span class="mnav-account-badge en-text">2</span>', false)
            ->assertDontSee('لوحة الإدارة');
    }

    public function test_enrollment_decision_links_into_the_course_or_the_requests_page(): void
    {
        $approved = CourseEnrollment::factory()->approved()->create();
        $rejected = CourseEnrollment::factory()->rejected()->create();

        $this->assertSame(route('learn.course', $approved->course->slug), (new EnrollmentDecided($approved))->actionUrl($approved->user));
        $this->assertSame(route('student.requests'), (new EnrollmentDecided($rejected))->actionUrl($rejected->user));
    }
}
