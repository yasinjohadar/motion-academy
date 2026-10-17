<?php

namespace App\Policies;

/**
 * Curriculum rows share the course permissions; ownership is checked on the parent course.
 */
class CourseSectionPolicy extends BasePolicy
{
    protected string $resource = 'courses';
}
