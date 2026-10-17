<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesCourseFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\CourseReorderRequest;
use App\Http\Requests\Admin\Course\CourseSectionRequest;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum sections. Managing the curriculum is editing the course, so every action
 * needs `admin.courses.edit` plus the course's own update policy (ownership).
 */
class CourseSectionController extends Controller implements HasMiddleware
{
    use ManagesCourseFiles;

    public static function middleware(): array
    {
        return [new Middleware('permission:admin.courses.edit')];
    }

    public function store(CourseSectionRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $course->sections()->create([
            ...$request->validated(),
            'order' => (int) $course->sections()->max('order') + 1,
        ]);

        return back()->with('success', 'تمت إضافة القسم.');
    }

    public function update(CourseSectionRequest $request, Course $course, CourseSection $section): RedirectResponse
    {
        $this->authorize('update', $course);

        $section->update($request->validated());

        return back()->with('success', 'تم تحديث القسم.');
    }

    public function destroy(Course $course, CourseSection $section): RedirectResponse
    {
        $this->authorize('update', $course);

        $lessons = $section->lessons()->get();
        $section->delete();

        foreach ($lessons as $lesson) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $lesson->hasStoredVideo() ? $lesson->video : null);
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $lesson->hasStoredAttachment() ? $lesson->attachment : null);
        }

        return back()->with('success', 'تم حذف القسم ودروسه.');
    }

    public function reorder(CourseReorderRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $request->validated('ids'));

        if ($course->sections()->whereIn('id', $ids)->count() !== count($ids)) {
            return back()->withErrors(['ids' => 'بعض الأقسام لا تتبع هذه الدورة.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                CourseSection::query()->whereKey($id)->update(['order' => $position + 1]);
            }
        });

        return back()->with('success', 'تم حفظ ترتيب الأقسام.');
    }
}
