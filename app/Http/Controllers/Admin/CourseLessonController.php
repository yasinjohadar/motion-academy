<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Http\Controllers\Concerns\ManagesCourseFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\CourseLessonRequest;
use App\Http\Requests\Admin\Course\CourseReorderRequest;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use App\Models\CourseSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Lessons inside a course's sections. Same gate as sections: `admin.courses.edit`
 * plus the course update policy.
 */
class CourseLessonController extends Controller implements HasMiddleware
{
    use ManagesCourseFiles;

    public static function middleware(): array
    {
        return [new Middleware('permission:admin.courses.edit')];
    }

    public function store(CourseLessonRequest $request, Course $course, CourseSection $section): RedirectResponse
    {
        $this->authorize('update', $course);

        $lesson = $section->lessons()->create([
            ...$this->lessonAttributes($request),
            'course_id' => $course->id,
            'order' => (int) $section->lessons()->max('order') + 1,
        ]);

        $this->syncAttachments($request, $lesson);

        return back()->with('success', 'تمت إضافة الدرس.');
    }

    public function update(CourseLessonRequest $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->authorize('update', $course);

        $attributes = $this->lessonAttributes($request, $lesson);

        $targetSectionId = $request->integer('course_section_id') ?: $lesson->course_section_id;
        if ($targetSectionId !== $lesson->course_section_id) {
            $target = $course->sections()->whereKey($targetSectionId)->first();
            if (! $target) {
                return back()->withErrors(['course_section_id' => 'القسم المختار لا يتبع هذه الدورة.']);
            }
            $attributes['course_section_id'] = $target->id;
            $attributes['order'] = (int) $target->lessons()->max('order') + 1;
        }

        $oldVideo = $lesson->hasStoredVideo() ? $lesson->video : null;
        $oldAttachment = $lesson->hasStoredAttachment() ? $lesson->attachment : null;

        $lesson->update($attributes);
        $this->syncAttachments($request, $lesson);

        if ($oldVideo !== null && $oldVideo !== $lesson->video) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $oldVideo);
        }
        if ($oldAttachment !== null && $oldAttachment !== $lesson->attachment) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $oldAttachment);
        }

        return back()->with('success', 'تم تحديث الدرس.');
    }

    public function destroy(Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->authorize('update', $course);

        $video = $lesson->hasStoredVideo() ? $lesson->video : null;
        $attachment = $lesson->hasStoredAttachment() ? $lesson->attachment : null;
        $files = $lesson->attachments()->whereNotNull('path')->pluck('path')->all();

        $lesson->delete();

        $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $video);
        $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $attachment);
        foreach ($files as $path) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $path);
        }

        return back()->with('success', 'تم حذف الدرس.');
    }

    public function reorder(CourseReorderRequest $request, Course $course, CourseSection $section): RedirectResponse
    {
        $this->authorize('update', $course);

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $request->validated('ids'));

        if ($section->lessons()->whereIn('id', $ids)->count() !== count($ids)) {
            return back()->withErrors(['ids' => 'يمكن إعادة ترتيب دروس القسم نفسه فقط.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                CourseLesson::query()->whereKey($id)->update(['order' => $position + 1]);
            }
        });

        return back()->with('success', 'تم حفظ ترتيب الدروس.');
    }

    /**
     * Map the request onto lesson columns, storing uploads and clearing fields that do
     * not apply to the chosen type.
     *
     * @return array<string, mixed>
     */
    private function lessonAttributes(CourseLessonRequest $request, ?CourseLesson $lesson = null): array
    {
        $type = LessonType::from((string) $request->input('type'));
        $source = $type === LessonType::Video ? VideoSource::tryFrom((string) $request->input('video_source')) : null;

        $attributes = [
            'title' => $request->string('title')->value(),
            'type' => $type,
            'video_source' => $source,
            'content' => $request->input('content') ?: null,
            'duration_seconds' => $request->integer('duration_seconds'),
            'is_preview' => $request->boolean('is_preview'),
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : true,
        ];

        if ($source === VideoSource::Upload) {
            $attributes['video'] = $request->hasFile('video_file')
                ? $this->storeCourseFile(Course::VIDEO_DISK, $request->file('video_file'), 'lessons', 'video_file')
                : ($lesson?->hasStoredVideo() ? $lesson->video : null);
        } else {
            $attributes['video'] = $source !== null ? ($request->input('video') ?: null) : null;
        }

        // The single `attachment` is the main file of a "file" lesson only; other
        // lessons list their resources in `attachments`.
        if ($type !== LessonType::File) {
            $attributes['attachment'] = null;
        } elseif ($request->hasFile('attachment_file')) {
            $attributes['attachment'] = $this->storeCourseFile(Course::VIDEO_DISK, $request->file('attachment_file'), 'attachments', 'attachment_file');
        } elseif (filled($request->input('attachment'))) {
            $attributes['attachment'] = (string) $request->input('attachment');
        } elseif ($request->boolean('remove_attachment')) {
            $attributes['attachment'] = null;
        }

        return $attributes;
    }

    /**
     * Make the lesson's resources match the submitted list: rows kept by id are
     * updated (a new upload replaces their file), new rows are created, rows left
     * out are deleted with their files. The submitted order is the display order.
     */
    private function syncAttachments(CourseLessonRequest $request, CourseLesson $lesson): void
    {
        if (! $request->has('attachments') && ! $request->boolean('attachments_present')) {
            return;
        }

        $existing = $lesson->attachments()->get()->keyBy('id');
        $keep = [];
        $orphans = [];

        foreach (array_values((array) $request->input('attachments', [])) as $index => $row) {
            $model = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;
            $isFile = ($row['type'] ?? 'link') === CourseLessonAttachment::TYPE_FILE;

            $values = [
                'type' => $isFile ? CourseLessonAttachment::TYPE_FILE : CourseLessonAttachment::TYPE_LINK,
                'title' => (string) $row['title'],
                'url' => $isFile ? null : (string) ($row['url'] ?? ''),
                'order' => $index + 1,
            ];

            $upload = $request->file("attachments.{$index}.file");
            if ($isFile && $upload) {
                if ($model?->path) {
                    $orphans[] = $model->path;
                }
                $values['path'] = $this->storeCourseFile(Course::VIDEO_DISK, $upload, 'attachments', "attachments.{$index}.file");
                $values['original_name'] = $upload->getClientOriginalName();
                $values['size'] = $upload->getSize();
            } elseif (! $isFile) {
                if ($model?->path) {
                    $orphans[] = $model->path;
                }
                $values['path'] = null;
                $values['original_name'] = null;
                $values['size'] = null;
            }

            $model = $model
                ? tap($model)->update($values)
                : $lesson->attachments()->create($values);
            $keep[] = $model->id;
        }

        foreach ($existing->except($keep) as $removed) {
            if ($removed->path) {
                $orphans[] = $removed->path;
            }
            $removed->delete();
        }

        foreach ($orphans as $path) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $path);
        }
    }
}
