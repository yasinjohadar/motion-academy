<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Notifications\Courses\EnrollmentDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['list', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("admin.enrollments.{$action}", 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    public function test_index_opens_on_the_pending_queue_with_counts(): void
    {
        CourseEnrollment::factory()->count(2)->create();
        CourseEnrollment::factory()->approved()->create();
        CourseEnrollment::factory()->rejected()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Enrollments/Index')
                ->where('filters.status', 'pending')
                ->where('counts.pending', 2)
                ->where('counts.approved', 1)
                ->where('counts.rejected', 1)
                ->where('counts.all', 4)
                ->has('enrollments.data', 2));

        $this->actingAs($this->admin())
            ->get(route('admin.enrollments.index', ['status' => 'all']))
            ->assertInertia(fn ($page) => $page->has('enrollments.data', 4));
    }

    public function test_approve_and_reject_notify_the_student(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $toApprove = CourseEnrollment::factory()->create();
        $toReject = CourseEnrollment::factory()->create();

        $this->actingAs($admin)->post(route('admin.enrollments.approve', $toApprove))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.enrollments.reject', $toReject), ['reason' => 'اكتملت الدفعة'])->assertSessionHas('success');

        $toApprove->refresh();
        $toReject->refresh();
        $this->assertTrue($toApprove->isApproved());
        $this->assertSame($admin->id, $toApprove->decided_by);
        $this->assertTrue($toReject->isRejected());
        $this->assertSame('اكتملت الدفعة', $toReject->admin_note);

        Notification::assertSentTo($toApprove->user, EnrollmentDecided::class);
        Notification::assertSentTo($toReject->user, EnrollmentDecided::class);
    }

    public function test_admin_adds_a_student_directly_and_cannot_double_add(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $student = User::factory()->create(['name_ar' => 'سارة']);
        $course = Course::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.enrollments.store'), ['user_id' => $student->id, 'course_id' => $course->id])
            ->assertSessionHasNoErrors();

        $enrollment = CourseEnrollment::query()->sole();
        $this->assertTrue($enrollment->isApproved());
        $this->assertSame('admin', $enrollment->source);

        $this->actingAs($admin)
            ->post(route('admin.enrollments.store'), ['user_id' => $student->id, 'course_id' => $course->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_seat_limit_is_enforced_when_approving(): void
    {
        $course = Course::factory()->published()->create(['max_students' => 1]);
        CourseEnrollment::factory()->approved()->create(['course_id' => $course->id]);
        $waiting = CourseEnrollment::factory()->create(['course_id' => $course->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.approve', $waiting))
            ->assertSessionHas('error');

        $this->assertTrue($waiting->fresh()->isPending());
    }

    public function test_bulk_approve_and_remove(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $requests = CourseEnrollment::factory()->count(3)->create();

        $this->actingAs($admin)
            ->post(route('admin.enrollments.bulk-approve'), ['ids' => $requests->modelKeys()])
            ->assertSessionHas('success');
        $this->assertSame(3, CourseEnrollment::query()->where('status', 'approved')->count());

        $this->actingAs($admin)->delete(route('admin.enrollments.destroy', $requests->first()));
        $this->assertSame(2, CourseEnrollment::query()->count());
    }

    public function test_student_search_excludes_staff_and_flags_existing_enrollment(): void
    {
        $course = Course::factory()->published()->create();
        $student = User::factory()->create(['name_ar' => 'خالدتجريبي المتعلم', 'email' => 'khaled@example.com']);
        CourseEnrollment::factory()->approved()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        // A term no factory-generated name contains (random names include «خالد»).
        $this->admin()->update(['name_ar' => 'خالدتجريبي المدير']);

        $this->actingAs($this->admin())
            ->getJson(route('admin.enrollments.students', ['q' => 'خالدتجريبي', 'course' => $course->id]))
            ->assertOk()
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.email', 'khaled@example.com')
            ->assertJsonPath('students.0.enrollment_status', 'approved');
    }

    public function test_courses_for_a_student_flag_where_they_already_stand(): void
    {
        $student = User::factory()->student()->create();
        $joined = Course::factory()->published()->create(['title' => 'منضم إليها']);
        $pending = Course::factory()->published()->create(['title' => 'طلب معلّق']);
        $full = Course::factory()->published()->create(['title' => 'ممتلئة', 'max_students' => 1]);
        $draft = Course::factory()->create(['title' => 'مسودة']);
        CourseEnrollment::factory()->approved()->create(['course_id' => $joined->id, 'user_id' => $student->id]);
        CourseEnrollment::factory()->create(['course_id' => $pending->id, 'user_id' => $student->id]);
        CourseEnrollment::factory()->approved()->create(['course_id' => $full->id]);

        $courses = collect($this->actingAs($this->admin())
            ->getJson(route('admin.enrollments.courses-for', $student))
            ->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->json('courses'))->keyBy('id');

        $this->assertSame('approved', $courses[$joined->id]['enrollment_status']);
        $this->assertSame('pending', $courses[$pending->id]['enrollment_status']);
        $this->assertTrue($courses[$full->id]['full']);
        $this->assertFalse($courses[$draft->id]['published']);
        // Published courses are listed before drafts.
        $this->assertSame($draft->id, $courses->keys()->last());
    }

    public function test_adding_from_the_users_list_enrolls_and_notifies_the_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->create();

        $this->actingAs($this->admin())
            ->from(route('admin.users.index'))
            ->post(route('admin.enrollments.store'), ['user_id' => $student->id, 'course_id' => $course->id, 'note' => 'أهلاً بك'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $enrollment = CourseEnrollment::query()->where('user_id', $student->id)->sole();
        $this->assertTrue($enrollment->isApproved());
        $this->assertSame(CourseEnrollment::SOURCE_ADMIN, $enrollment->source);
        Notification::assertSentTo($student, EnrollmentDecided::class);
    }

    public function test_listing_courses_for_a_student_needs_the_add_permission(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->panelUserWithoutPermissions())
            ->getJson(route('admin.enrollments.courses-for', $student))
            ->assertForbidden();
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $enrollment = CourseEnrollment::factory()->create();

        $this->actingAs($user)->get(route('admin.enrollments.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.enrollments.approve', $enrollment))->assertForbidden();
    }
}
