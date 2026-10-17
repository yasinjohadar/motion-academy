<?php

namespace App\Http\Requests\Admin\Course;

use App\Models\CourseCategory;

/**
 * Same rules as creating; the category being edited is excluded from the
 * slug uniqueness check and from being its own parent.
 */
class CourseCategoryUpdateRequest extends CourseCategoryStoreRequest
{
    protected function categoryId(): ?int
    {
        $category = $this->route('course_category');

        return $category instanceof CourseCategory ? $category->id : (int) $category;
    }
}
