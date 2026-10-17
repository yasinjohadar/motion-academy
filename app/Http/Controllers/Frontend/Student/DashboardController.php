<?php

namespace App\Http\Controllers\Frontend\Student;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Frontend\CourseCatalog;
use App\Services\Learning\LearningProgress;
use App\Services\Seo\SeoPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's own area: overview, courses with progress, and the history of
 * enrollment requests.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly LearningProgress $progress,
        private readonly CourseCatalog $catalog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $enrollments = $this->enrollments($user);
        $approved = $enrollments->filter->isApproved();
        $active = $approved->reject->isCompleted();

        // The course touched most recently is the one to continue.
        $current = $active->sortByDesc(fn (CourseEnrollment $e) => $e->last_accessed_at?->timestamp ?? 0)->first();

        $completedLessons = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at');

        return view('frontend.student.dashboard', [
            'seo' => SeoPage::make('لوحتي')->noindex(follow: false),
            'current' => $current,
            'currentLesson' => $current ? $this->progress->resumeLesson($user, $current->course, $current) : null,
            'active' => $active->values(),
            'doneCounts' => $this->doneCounts($user),
            'pending' => $enrollments->filter->isPending()->values(),
            'stats' => [
                'active' => $active->count(),
                'completed' => $approved->filter->isCompleted()->count(),
                'lessons' => (clone $completedLessons)->count(),
                'minutes' => (int) round((clone $completedLessons)
                    ->join('course_lessons', 'course_lessons.id', '=', 'lesson_progress.course_lesson_id')
                    ->sum('course_lessons.duration_seconds') / 60),
            ],
            'notifications' => AppNotification::query()->forUser($user)->latest('created_at')->take(5)->get()
                ->map(fn (AppNotification $notification) => $notification->toAdminArray()),
            'suggestions' => $approved->isEmpty()
                ? $this->catalog->visible()
                    ->whereNotIn('id', $enrollments->pluck('course_id'))
                    ->orderByDesc('is_featured')
                    ->latest('published_at')
                    ->take(3)
                    ->get()
                : collect(),
        ]);
    }

    public function courses(Request $request): View
    {
        $tab = $request->query('tab') === 'completed' ? 'completed' : 'active';
        $approved = $this->enrollments($request->user())->filter->isApproved();
        $completed = $approved->filter->isCompleted();
        $active = $approved->reject->isCompleted();

        return view('frontend.student.courses', [
            'seo' => SeoPage::make('دوراتي')->noindex(follow: false),
            'tab' => $tab,
            'doneCounts' => $this->doneCounts($request->user()),
            'counts' => ['active' => $active->count(), 'completed' => $completed->count()],
            'enrollments' => ($tab === 'completed' ? $completed : $active)
                ->sortByDesc(fn (CourseEnrollment $e) => $e->last_accessed_at?->timestamp ?? $e->decided_at?->timestamp ?? 0)
                ->values(),
        ]);
    }

    public function requests(Request $request): View
    {
        return view('frontend.student.requests', [
            'seo' => SeoPage::make('طلباتي واشتراكاتي')->noindex(follow: false),
            'enrollments' => $this->enrollments($request->user()),
        ]);
    }

    /**
     * Finished lessons per course, for "3 of 10 lessons".
     *
     * @return array<int, int> course id => count
     */
    private function doneCounts(User $user): array
    {
        return LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->selectRaw('course_id, count(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Every enrollment of the student whose course still exists, newest first.
     *
     * @return Collection<int, CourseEnrollment>
     */
    private function enrollments(User $user): Collection
    {
        return $user->enrollments()
            ->with([
                'course' => fn ($query) => $query->with(['category', 'instructor:id,name']),
                'lastLesson:id,title,course_id',
            ])
            ->whereHas('course')
            ->latest('updated_at')
            ->get();
    }
}
