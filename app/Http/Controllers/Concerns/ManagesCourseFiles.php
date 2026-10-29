<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Stores and removes course/lesson files.
 *
 * Duplicating a course copies its rows but not its files, so several rows can point
 * at one stored path. A file is therefore only deleted once nothing references it.
 */
trait ManagesCourseFiles
{
    /**
     * @throws ValidationException when the upload fails — never a silent null that
     *                             would clear the column and delete the old file
     */
    protected function storeCourseFile(string $disk, UploadedFile $file, string $directory, string $field = 'file'): string
    {
        $path = app(AppStorageManager::class)->storeUploadedFile($disk, $file, $directory);

        if ($path === false) {
            throw ValidationException::withMessages([$field => 'تعذّر رفع الملف. لم يتم تغيير الملف الحالي.']);
        }

        return $path;
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
