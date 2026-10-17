<?php

namespace App\Notifications\Courses;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\CourseEnrollment;
use App\Notifications\BaseNotification;

/**
 * Tells the student that their request was approved or rejected, or that an admin
 * added them to a course.
 */
class EnrollmentDecided extends BaseNotification
{
    public function __construct(public CourseEnrollment $enrollment)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Courses;
    }

    public function level(): NotificationLevel
    {
        return $this->enrollment->isApproved() ? NotificationLevel::Success : NotificationLevel::Warning;
    }

    public function title(object $notifiable): string
    {
        return $this->enrollment->isApproved() ? 'تم قبولك في الدورة' : 'تعذّر قبول طلب الانضمام';
    }

    public function message(object $notifiable): string
    {
        $course = $this->enrollment->course?->title;

        if ($this->enrollment->isApproved()) {
            return "أصبحت منضماً إلى دورة «{$course}». نتمنى لك رحلة تعلّم ممتعة!";
        }

        $reason = $this->enrollment->admin_note;

        return "لم تتم الموافقة على طلب انضمامك إلى «{$course}»".($reason ? ": {$reason}" : '.');
    }

    public function actionUrl(object $notifiable): ?string
    {
        // Approved: straight into the course. Rejected: the request history, with the reason.
        return $this->enrollment->isApproved()
            ? route('learn.course', $this->enrollment->course->slug)
            : route('student.requests');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->enrollment->course_id,
            'status' => $this->enrollment->status,
        ];
    }
}
