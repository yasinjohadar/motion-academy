<?php

namespace App\Notifications\Courses;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\CourseEnrollment;
use App\Notifications\BaseNotification;

/**
 * Sent to the admins who can decide on enrollments when a student asks to join.
 */
class EnrollmentRequested extends BaseNotification
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
        return NotificationLevel::Info;
    }

    public function title(object $notifiable): string
    {
        return 'طلب انضمام جديد';
    }

    public function message(object $notifiable): string
    {
        return "{$this->enrollment->user?->name} يطلب الانضمام إلى «{$this->enrollment->course?->title}».";
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.enrollments.index', ['status' => CourseEnrollment::STATUS_PENDING]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->enrollment->course_id,
        ];
    }
}
