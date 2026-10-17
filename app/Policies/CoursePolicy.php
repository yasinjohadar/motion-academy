<?php

namespace App\Policies;

/**
 * Instructors own their courses: editing or deleting someone else's needs the -any permission.
 */
class CoursePolicy extends BasePolicy
{
    protected string $resource = 'courses';

    protected ?string $ownerColumn = 'instructor_id';
}
