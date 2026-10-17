<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use Illuminate\Http\RedirectResponse;

/**
 * The old lesson address (/lessons/{id}). Lessons now live in the player under
 * their course (/learn/{course}/{lesson}), which also checks enrollment.
 */
class LessonController extends Controller
{
    public function show(int $id): RedirectResponse
    {
        $lesson = CourseLesson::query()
            ->where('is_published', true)
            ->with('course:id,slug')
            ->findOrFail($id);

        abort_unless($lesson->course, 404);

        return redirect()->route('learn.lesson', [$lesson->course->slug, $lesson->id], 301);
    }
}
