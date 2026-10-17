<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything about a student moving through a course: the lesson order, which
 * lessons are done, where to resume, and the percentage kept on the enrollment.
 *
 * Only published lessons count — a lesson the instructor hides neither blocks
 * 100% nor shows up in the player.
 */
class LearningProgress
{
    /** A video watched this far counts as finished. */
    public const AUTO_COMPLETE_RATIO = 0.9;

    /** @var array<int, Collection<int, CourseLesson>> */
    private array $curricula = [];

    /**
     * Published lessons in the order a student takes them: by section, then lesson.
     *
     * @return Collection<int, CourseLesson>
     */
    public function curriculum(Course $course): Collection
    {
        return $this->curricula[$course->id] ??= CourseLesson::query()
            ->select('course_lessons.*')
            ->with('section')
            ->join('course_sections', 'course_sections.id', '=', 'course_lessons.course_section_id')
            ->where('course_lessons.course_id', $course->id)
            ->where('course_lessons.is_published', true)
            ->orderBy('course_sections.order')
            ->orderBy('course_sections.id')
            ->orderBy('course_lessons.order')
            ->orderBy('course_lessons.id')
            ->get();
    }

    /**
     * @return array<int, LessonProgress> keyed by lesson id
     */
    public function progressMap(User $user, Course $course): array
    {
        return LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('course_lesson_id')
            ->all();
    }

    public function enrollment(User $user, Course $course): ?CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', CourseEnrollment::STATUS_APPROVED)
            ->first();
    }

    public function markComplete(User $user, CourseLesson $lesson): LessonProgress
    {
        $progress = $this->row($user, $lesson);

        if ($progress->completed_at === null) {
            $progress->completed_at = now();
            $progress->save();
        }

        $this->refreshFor($user, $lesson);

        return $progress;
    }

    public function markIncomplete(User $user, CourseLesson $lesson): LessonProgress
    {
        $progress = $this->row($user, $lesson);
        $progress->completed_at = null;
        $progress->save();

        $this->refreshFor($user, $lesson);

        return $progress;
    }

    /**
     * Remember where the video stopped; finishing most of it completes the lesson.
     * The lesson's own duration wins over what the browser reports, when known.
     */
    public function recordPosition(User $user, CourseLesson $lesson, int $seconds, ?int $reportedDuration = null): LessonProgress
    {
        $progress = $this->row($user, $lesson);
        $progress->position_seconds = max(0, $seconds);
        $progress->save();

        $duration = $lesson->duration_seconds > 0 ? $lesson->duration_seconds : (int) $reportedDuration;

        if ($progress->completed_at === null && $duration > 0 && $seconds >= $duration * self::AUTO_COMPLETE_RATIO) {
            return $this->markComplete($user, $lesson);
        }

        return $progress;
    }

    /**
     * The student opened this lesson: it becomes the one to resume.
     */
    public function touch(CourseEnrollment $enrollment, CourseLesson $lesson): void
    {
        $enrollment->forceFill([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
        ])->save();
    }

    public function refresh(CourseEnrollment $enrollment): CourseEnrollment
    {
        $course = $enrollment->loadMissing('course')->course;
        $lessonIds = $this->curriculum($course)->pluck('id')->all();
        $total = count($lessonIds);

        $done = $total === 0 ? 0 : LessonProgress::query()
            ->where('user_id', $enrollment->user_id)
            ->whereIn('course_lesson_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->count();

        $percent = $total === 0 ? 0 : (int) floor($done * 100 / $total);

        $enrollment->forceFill([
            'progress_percent' => $percent,
            'completed_at' => $percent === 100 ? ($enrollment->completed_at ?? now()) : null,
        ])->save();

        return $enrollment;
    }

    /**
     * The last lesson opened; otherwise the first unfinished one; otherwise the first.
     */
    public function resumeLesson(User $user, Course $course, ?CourseEnrollment $enrollment = null): ?CourseLesson
    {
        $lessons = $this->curriculum($course);

        if ($enrollment?->last_lesson_id && ($last = $lessons->firstWhere('id', $enrollment->last_lesson_id))) {
            return $last;
        }

        $done = $this->progressMap($user, $course);

        return $lessons->first(fn (CourseLesson $lesson) => ! ($done[$lesson->id] ?? null)?->isCompleted())
            ?? $lessons->first();
    }

    /**
     * @return array{previous: CourseLesson|null, next: CourseLesson|null, position: int, total: int}
     */
    public function neighbours(Course $course, CourseLesson $lesson): array
    {
        $lessons = $this->curriculum($course)->values();
        $index = $lessons->search(fn (CourseLesson $item) => $item->id === $lesson->id);
        $index = $index === false ? 0 : $index;

        return [
            'previous' => $index > 0 ? $lessons->get($index - 1) : null,
            'next' => $lessons->get($index + 1),
            'position' => $index + 1,
            'total' => $lessons->count(),
        ];
    }

    private function row(User $user, CourseLesson $lesson): LessonProgress
    {
        return LessonProgress::query()->firstOrNew(
            ['user_id' => $user->id, 'course_lesson_id' => $lesson->id],
            ['course_id' => $lesson->course_id, 'position_seconds' => 0],
        );
    }

    private function refreshFor(User $user, CourseLesson $lesson): void
    {
        $enrollment = $this->enrollment($user, $lesson->loadMissing('course')->course);

        if ($enrollment) {
            $this->refresh($enrollment);
        }
    }
}
