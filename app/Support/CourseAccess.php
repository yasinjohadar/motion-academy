<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;

/**
 * Who may open a course's lessons: a student whose enrollment was approved, or
 * staff who edit courses (to check what students will see).
 */
class CourseAccess
{
    public static function canLearn(?User $user, Course $course): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can('admin.courses.edit')) {
            return true;
        }

        return CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', CourseEnrollment::STATUS_APPROVED)
            ->exists();
    }
}
