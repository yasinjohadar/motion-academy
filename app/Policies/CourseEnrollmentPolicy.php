<?php

namespace App\Policies;

/**
 * Enrollments have no owner on the admin side: the permission alone decides.
 */
class CourseEnrollmentPolicy extends BasePolicy
{
    protected string $resource = 'enrollments';
}
