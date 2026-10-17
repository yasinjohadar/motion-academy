<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseStatus;
use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use App\Support\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Enrollment requests from students, and admins adding students to courses.
 */
class CourseEnrollmentController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(private readonly EnrollmentService $enrollments) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'enrollments',
            false,
            [
                'edit' => ['approve', 'reject', 'bulkApprove'],
                'admin.enrollments.create' => ['students', 'coursesFor'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $filters = [
            // The pending queue is what an admin opens this page for; "all" lifts the filter.
            'status' => $this->statusFilter((string) $request->string('status', CourseEnrollment::STATUS_PENDING)),
            'course' => ctype_digit((string) $request->string('course')) ? (string) $request->string('course') : '',
            'query' => trim((string) $request->string('query')),
        ];

        $enrollments = CourseEnrollment::query()
            ->with(['user:id,name,email,phone', 'course:id,title,slug,thumbnail', 'decider:id,name'])
            ->when($filters['status'] !== 'all', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['course'] !== '', fn (Builder $q) => $q->where('course_id', (int) $filters['course']))
            ->when($filters['query'] !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u
                ->where('name', 'like', '%'.$filters['query'].'%')
                ->orWhere('email', 'like', '%'.$filters['query'].'%')))
            // Pending first, oldest request first — the queue is worked in order.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending' THEN created_at END ASC")
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CourseEnrollment $enrollment) => $enrollment->toAdminArray());

        $counts = CourseEnrollment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Enrollments/Index', [
            'enrollments' => $enrollments,
            'filters' => $filters,
            'counts' => [
                'all' => (int) $counts->sum(),
                'pending' => (int) ($counts[CourseEnrollment::STATUS_PENDING] ?? 0),
                'approved' => (int) ($counts[CourseEnrollment::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($counts[CourseEnrollment::STATUS_REJECTED] ?? 0),
            ],
            'courses' => Course::query()
                ->orderBy('title')
                ->get(['id', 'title', 'max_students'])
                ->map(fn (Course $course) => ['id' => $course->id, 'title' => $course->title, 'max_students' => $course->max_students])
                ->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * Search accounts to add (name or email). Staff with panel roles are excluded.
     */
    public function students(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));
        $courseId = $request->integer('course');

        if (mb_strlen($query) < 2) {
            return response()->json(['students' => []]);
        }

        $students = User::query()
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', AccountType::ADMIN))
            ->with(['enrollments' => fn ($e) => $e->where('course_id', $courseId)])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'enrollment_status' => $user->enrollments->first()?->status,
            ]);

        return response()->json(['students' => $students]);
    }

    /**
     * Courses to offer when adding one student from the users list, with where
     * that student already stands in each (so the picker can say "already in").
     */
    public function coursesFor(User $user): JsonResponse
    {
        $statuses = $user->enrollments()->pluck('status', 'course_id');
        $taken = CourseEnrollment::query()
            ->where('status', CourseEnrollment::STATUS_APPROVED)
            ->selectRaw('course_id, count(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id');

        $courses = Course::query()
            ->with('category:id,name')
            ->orderByRaw('case when status = ? then 0 else 1 end', [CourseStatus::Published->value])
            ->orderBy('title')
            ->get(['id', 'title', 'status', 'course_category_id', 'max_students', 'is_free'])
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'category' => $course->category?->name,
                'published' => $course->status === CourseStatus::Published,
                'status_label' => $course->status->label(),
                'is_free' => (bool) $course->is_free,
                'full' => $course->max_students !== null && (int) ($taken[$course->id] ?? 0) >= $course->max_students,
                'enrollment_status' => $statuses[$course->id] ?? null,
            ]);

        return response()->json([
            'student' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'courses' => $courses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['user_id' => 'الطالب', 'course_id' => 'الدورة', 'note' => 'الملاحظة']);

        $student = User::query()->findOrFail($validated['user_id']);
        $course = Course::query()->findOrFail($validated['course_id']);

        $this->enrollments->add($student, $course, $request->user(), $validated['note'] ?? null);

        return back()->with('success', "تمت إضافة {$student->name} إلى «{$course->title}».");
    }

    public function approve(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        try {
            $this->enrollments->approve($enrollment, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'تم قبول '.$enrollment->user?->name.' في «'.$enrollment->course?->title.'».');
    }

    public function reject(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['nullable', 'string', 'max:500']],
            [],
            ['reason' => 'سبب الرفض'],
        );

        $this->enrollments->reject($enrollment, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'تم رفض طلب '.$enrollment->user?->name.'.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $approved = 0;
        $skipped = 0;

        CourseEnrollment::query()
            ->whereIn('id', $validated['ids'])
            ->where('status', CourseEnrollment::STATUS_PENDING)
            ->with(['course', 'user'])
            ->orderBy('created_at')
            ->get()
            ->each(function (CourseEnrollment $enrollment) use ($request, &$approved, &$skipped) {
                try {
                    $this->enrollments->approve($enrollment, $request->user());
                    $approved++;
                } catch (ValidationException) {
                    $skipped++;
                }
            });

        $message = "تم قبول {$approved} طلب.";
        if ($skipped > 0) {
            $message .= " وتُرك {$skipped} لاكتمال مقاعد دوراتهم.";
        }

        return back()->with($skipped > 0 && $approved === 0 ? 'error' : 'success', $message);
    }

    private function statusFilter(string $status): string
    {
        return $status === 'all' || array_key_exists($status, CourseEnrollment::STATUSES)
            ? $status
            : CourseEnrollment::STATUS_PENDING;
    }

    public function destroy(CourseEnrollment $enrollment): RedirectResponse
    {
        $name = $enrollment->user?->name;
        $enrollment->delete();

        return back()->with('success', "تمت إزالة {$name} من الدورة.");
    }
}
