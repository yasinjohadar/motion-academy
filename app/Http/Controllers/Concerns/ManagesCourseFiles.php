<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\UploadedFile;

/**
 * Stores and removes course/lesson files.
 *
 * Duplicating a course copies its rows but not its files, so several rows can point
 * at one stored path. A file is therefore only deleted once nothing references it.
 */
trait ManagesCourseFiles
{
    protected function storeCourseFile(string $disk, UploadedFile $file, string $directory): ?string
    {
        $path = app(AppStorageManager::class)->storeUploadedFile($disk, $file, $directory);

        return $path !== false ? $path : null;
    }

    protected function deleteCourseFileIfUnused(string $disk, ?string $path): void
    {
        if (! Course::isStoredPath($path) || $this->courseFileIsReferenced($disk, (string) $path)) {
            return;
        }

        app(AppStorageManager::class)->delete($disk, $path);
    }

    private function courseFileIsReferenced(string $disk, string $path): bool
    {
        if ($disk === Course::IMAGE_DISK) {
            return Course::withTrashed()->where('thumbnail', $path)->exists();
        }

        return Course::withTrashed()->where('intro_video', $path)->exists()
            || CourseLesson::query()->where('video', $path)->orWhere('attachment', $path)->exists()
            || CourseLessonAttachment::query()->where('path', $path)->exists();
    }
}
