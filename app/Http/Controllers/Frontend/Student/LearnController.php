<?php

namespace App\Http\Controllers\Frontend\Student;

use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Services\Learning\LearningProgress;
use App\Services\Seo\SeoPage;
use App\Support\CourseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The lesson player and the two calls it makes back: "I finished this lesson"
 * and "the video is here". Open to approved students and course staff only.
 */
class LearnController extends Controller
{
    public function __construct(private readonly LearningProgress $progress) {}

    /**
     * /learn/{course}: straight to the lesson to resume.
     */
    public function course(Request $request, string $slug): RedirectResponse
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();

        if ($denied = $this->deny($request, $course)) {
            return $denied;
        }

        $enrollment = $this->progress->enrollment($request->user(), $course);
        $lesson = $this->progress->resumeLesson($request->user(), $course, $enrollment);

        if (! $lesson) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('enrollment_success', 'لم تُنشر دروس هذه الدورة بعد — سنبلغك عند إضافتها.');
        }

        return redirect()->route('learn.lesson', [$course->slug, $lesson->id]);
    }

    public function lesson(Request $request, string $slug, int $lessonId): View|RedirectResponse
    {
        $course = Course::query()->where('slug', $slug)->with('instructor:id,name')->firstOrFail();

        if ($denied = $this->deny($request, $course)) {
            return $denied;
        }

        $lesson = $this->publishedLesson($course, $lessonId);
        $user = $request->user();

        $enrollment = $this->progress->enrollment($user, $course);
        if ($enrollment) {
            $this->progress->touch($enrollment, $lesson);
        }

        $curriculum = $this->progress->curriculum($course);
        $progressMap = $this->progress->progressMap($user, $course);
        $completed = collect($progressMap)->filter->isCompleted()->keys()->all();

        return view('frontend.student.learn', [
            'seo' => SeoPage::make($lesson->title.' — '.$course->title)->noindex(follow: false),
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'sections' => $curriculum->groupBy('course_section_id')->map(fn ($lessons) => [
                'section' => $lessons->first()->section,
                'lessons' => $lessons,
            ])->values(),
            'completedIds' => $completed,
            'isCompleted' => in_array($lesson->id, $completed, true),
            'position' => $progressMap[$lesson->id]->position_seconds ?? 0,
            'media' => $this->media($lesson),
            'resources' => $lesson->attachments()->get(),
            'neighbours' => $this->progress->neighbours($course, $lesson),
            'percent' => $enrollment->progress_percent
                ?? ($curriculum->isEmpty() ? 0 : (int) floor(count($completed) * 100 / $curriculum->count())),
        ]);
    }

    /**
     * Mark the lesson done (or not done again). JSON for the player, a redirect
     * when JavaScript is off.
     */
    public function complete(Request $request, CourseLesson $lesson): JsonResponse|RedirectResponse
    {
        $course = $this->courseOf($request, $lesson);
        $done = $request->boolean('completed', true);

        $done
            ? $this->progress->markComplete($request->user(), $lesson)
            : $this->progress->markIncomplete($request->user(), $lesson);

        $enrollment = $this->progress->enrollment($request->user(), $course);

        if (! $request->expectsJson()) {
            return back();
        }

        return response()->json([
            'completed' => $done,
            'percent' => $enrollment->progress_percent ?? 0,
            'course_completed' => (bool) $enrollment?->isCompleted(),
        ]);
    }

    public function position(Request $request, CourseLesson $lesson): JsonResponse
    {
        $this->courseOf($request, $lesson);

        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $row = $this->progress->recordPosition(
            $request->user(),
            $lesson,
            (int) $data['seconds'],
            isset($data['duration']) ? (int) $data['duration'] : null,
        );

        $enrollment = $this->progress->enrollment($request->user(), $lesson->course);

        return response()->json([
            'completed' => $row->isCompleted(),
            'percent' => $enrollment->progress_percent ?? 0,
            'course_completed' => (bool) $enrollment?->isCompleted(),
        ]);
    }

    private function deny(Request $request, Course $course): ?RedirectResponse
    {
        if (CourseAccess::canLearn($request->user(), $course)) {
            return null;
        }

        return redirect()
            ->to(route('courses.show', $course->slug).'#enroll')
            ->withErrors(['enrollment' => 'دروس هذه الدورة متاحة للطلاب المنضمّين إليها فقط.']);
    }

    private function publishedLesson(Course $course, int $lessonId): CourseLesson
    {
        return CourseLesson::query()
            ->where('course_id', $course->id)
            ->where('is_published', true)
            ->with('section')
            ->findOrFail($lessonId);
    }

    /**
     * The lesson's course, after checking the student may learn it.
     */
    private function courseOf(Request $request, CourseLesson $lesson): Course
    {
        abort_unless($lesson->is_published && $lesson->loadMissing('course')->course, 404);
        abort_unless(CourseAccess::canLearn($request->user(), $lesson->course), 403);

        return $lesson->course;
    }

    /**
     * What the player shows: an embed (YouTube/Vimeo), a <video> file, the
     * lesson's text, or a download.
     *
     * @return array{kind: string, src: string|null, content: string|null, attachment: string|null}
     */
    private function media(CourseLesson $lesson): array
    {
        $attachment = $lesson->attachmentUrl();

        if ($lesson->type === LessonType::Video) {
            $embed = $lesson->video_source?->embedUrl($lesson->video);
            $src = $embed ?? $lesson->videoUrl();

            return [
                'kind' => $src ? ($embed ? 'embed' : 'video') : 'missing',
                'src' => $src,
                'content' => $lesson->content,
                'attachment' => $attachment,
            ];
        }

        return [
            'kind' => $lesson->type === LessonType::File ? 'file' : 'text',
            'src' => null,
            'content' => $lesson->content,
            'attachment' => $attachment,
        ];
    }
}
