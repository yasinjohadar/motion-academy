<?php

namespace Tests\Feature\Frontend;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Notifications\Courses\EnrollmentRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        return Course::factory()->published()->create($attributes);
    }

    public function test_a_guest_is_asked_to_log_in_and_returned_to_the_course(): void
    {
        $course = $this->course();

        $this->get(route('courses.show', $course->slug))
            ->assertOk()
            ->assertSee('سجّل دخولك لطلب الانضمام');

        $this->post(route('courses.enroll', $course->slug))->assertRedirect(route('login'));

        $this->get(route('courses.enroll.start', $course->slug))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('courses.enroll.start', $course->slug))
            ->assertRedirect(route('courses.show', $course->slug).'#enroll');
    }

    public function test_a_student_requests_to_join_and_admins_are_notified(): void
    {
        Notification::fake();
        Permission::findOrCreate('admin.enrollments.edit', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo('admin.enrollments.edit');

        $student = User::factory()->create();
        $course = $this->course();

        $this->actingAs($student)
            ->post(route('courses.enroll', $course->slug), ['message' => 'أريد التعلّم'])
            ->assertRedirect()
            ->assertSessionHas('enrollment_success');

        $enrollment = CourseEnrollment::query()->sole();
        $this->assertTrue($enrollment->isPending());
        $this->assertSame('أريد التعلّم', $enrollment->message);
        Notification::assertSentTo($admin, EnrollmentRequested::class);

        $this->actingAs($student)
            ->get(route('courses.show', $course->slug))
            ->assertSee('طلبك قيد المراجعة');
    }

    public function test_duplicate_and_full_requests_are_refused(): void
    {
        $student = User::factory()->create();
        $course = $this->course(['max_students' => 1]);

        $this->actingAs($student)->post(route('courses.enroll', $course->slug));
        $this->actingAs($student)
            ->post(route('courses.enroll', $course->slug))
            ->assertSessionHasErrors('enrollment');
        $this->assertSame(1, CourseEnrollment::query()->count());

        CourseEnrollment::factory()->approved()->create(['course_id' => $course->id]);
        $other = User::factory()->create();
        $this->actingAs($other)
            ->post(route('courses.enroll', $course->slug))
            ->assertSessionHasErrors('enrollment');
        $this->actingAs($other)
            ->get(route('courses.show', $course->slug))
            ->assertSee('اكتمل عدد المقاعد');
    }

    public function test_a_pending_request_can_be_withdrawn_and_a_rejected_one_renewed(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $this->actingAs($student)->post(route('courses.enroll', $course->slug));
        $this->actingAs($student)->delete(route('courses.enroll.cancel', $course->slug));
        $this->assertSame(0, CourseEnrollment::query()->count());

        $rejected = CourseEnrollment::factory()->rejected()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $this->actingAs($student)
            ->get(route('courses.show', $course->slug))
            ->assertSee('أعد طلب الانضمام');

        $this->actingAs($student)->post(route('courses.enroll', $course->slug));
        $this->assertTrue($rejected->fresh()->isPending());
        $this->assertSame(1, CourseEnrollment::query()->count());
    }

    public function test_old_my_courses_address_moves_to_the_dashboard(): void
    {
        $student = User::factory()->create();
        $approved = CourseEnrollment::factory()->approved()->create(['user_id' => $student->id]);
        $pending = CourseEnrollment::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->get(route('my-courses'))
            ->assertStatus(301)
            ->assertRedirect('/dashboard/courses');

        $this->actingAs($student)
            ->get(route('student.requests'))
            ->assertOk()
            ->assertSee($approved->course->title)
            ->assertSee($pending->course->title)
            ->assertSee('بانتظار الموافقة');
    }

    public function test_registration_makes_a_student_who_lands_on_the_dashboard(): void
    {
        $this->post(route('register'), [
            'name' => 'طالب جديد',
            'email' => 'student@example.com',
            'country_code' => '963',
            'phone' => '991234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertTrue(User::query()->where('email', 'student@example.com')->sole()->hasRole('student'));
        $this->assertTrue(Role::query()->where('name', 'student')->exists());
    }
}
