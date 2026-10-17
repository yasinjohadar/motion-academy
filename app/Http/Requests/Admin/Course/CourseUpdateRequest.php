<?php

namespace App\Http\Requests\Admin\Course;

use App\Enums\VideoSource;
use App\Models\Course;

/**
 * Same rules as creating; the edited course is excluded from slug uniqueness, and an
 * already-uploaded intro video satisfies the "upload" source without a new file.
 */
class CourseUpdateRequest extends CourseStoreRequest
{
    protected function courseId(): ?int
    {
        return $this->course()?->id;
    }

    protected function publishedLessonsCount(): int
    {
        return (int) $this->course()?->lessons_count;
    }

    protected function hasExistingUploadedIntro(): bool
    {
        $course = $this->course();

        return $course !== null
            && $course->intro_video_source === VideoSource::Upload
            && filled($course->intro_video);
    }

    private function course(): ?Course
    {
        $course = $this->route('course');

        return $course instanceof Course ? $course : null;
    }
}
