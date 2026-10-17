<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Notifications\Courses\EnrollmentDecided;
use App\Notifications\Courses\EnrollmentRequested;
use App\Support\NotificationRecipients;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The enrollment rules in one place, for both the student's request on the public
 * site and the admin's decisions in the control panel.
 */
class EnrollmentService
{
    /**
     * A student asks to join. Re-asking after a rejection reuses the same row.
     *
     * @throws ValidationException
     */
    public function request(User $student, Course $course, ?string $message = null): CourseEnrollment
    {
        $existing = CourseEnrollment::query()->where('course_id', $course->id)->where('user_id', $student->id)->first();

        if ($existing?->isApproved()) {
            throw ValidationException::withMessages(['enrollment' => 'أنت منضم بالفعل إلى هذه الدورة.']);
        }

        if ($existing?->isPending()) {
            throw ValidationException::withMessages(['enrollment' => 'طلبك قيد المراجعة بالفعل.']);
        }

        if ($course->isFull()) {
            throw ValidationException::withMessages(['enrollment' => 'عذراً، اكتمل عدد المقاعد في هذه الدورة.']);
        }

        $enrollment = CourseEnrollment::query()->updateOrCreate(
            ['course_id' => $course->id, 'user_id' => $student->id],
            [
                'status' => CourseEnrollment::STATUS_PENDING,
                'source' => CourseEnrollment::SOURCE_REQUEST,
                'message' => $message,
                'admin_note' => null,
                'decided_by' => null,
                'decided_at' => null,
            ],
        );

        $recipients = NotificationRecipients::forPermission('admin.enrollments.edit');
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EnrollmentRequested($enrollment->load(['user', 'course'])));
        }

        return $enrollment;
    }

    /**
     * An admin puts a student straight into a course (no request needed).
     *
     * @throws ValidationException
     */
    public function add(User $student, Course $course, User $admin, ?string $note = null): CourseEnrollment
    {
        $existing = CourseEnrollment::query()->where('course_id', $course->id)->where('user_id', $student->id)->first();

        if ($existing?->isApproved()) {
            throw ValidationException::withMessages(['user_id' => 'هذا الطالب منضم بالفعل إلى الدورة.']);
        }

        $enrollment = $existing ?? new CourseEnrollment([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'source' => CourseEnrollment::SOURCE_ADMIN,
        ]);

        $this->approve($enrollment, $admin, $note);

        return $enrollment;
    }

    /**
     * @throws ValidationException
     */
    public function approve(CourseEnrollment $enrollment, User $admin, ?string $note = null): void
    {
        $course = $enrollment->course ?? Course::query()->findOrFail($enrollment->course_id);

        if (! $enrollment->isApproved() && $course->isFull()) {
            throw ValidationException::withMessages(['enrollment' => "اكتمل عدد المقاعد في «{$course->title}» ({$course->max_students})."]);
        }

        $enrollment->approve($admin, $note);
        $this->notifyStudent($enrollment);
    }

    public function reject(CourseEnrollment $enrollment, User $admin, ?string $reason = null): void
    {
        $enrollment->reject($admin, $reason);
        $this->notifyStudent($enrollment);
    }

    private function notifyStudent(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['user', 'course']);
        $enrollment->user?->notify(new EnrollmentDecided($enrollment));
    }
}
