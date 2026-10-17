<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Services\Courses\EnrollmentService;
use App\Services\Frontend\CourseCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A signed-in student asking to join a course, or withdrawing that request.
 * Their courses and requests are listed in the student dashboard.
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly CourseCatalog $catalog,
        private readonly EnrollmentService $enrollments,
    ) {}

    public function store(Request $request, string $slug): RedirectResponse
    {
        $validated = $request->validate(
            ['message' => ['nullable', 'string', 'max:1000']],
            [],
            ['message' => 'رسالتك'],
        );

        $course = $this->catalog->visible()->where('slug', $slug)->firstOrFail();

        $this->enrollments->request($request->user(), $course, $validated['message'] ?? null);

        return redirect()
            ->to(route('courses.show', $course->slug).'#enroll')
            ->with('enrollment_success', 'تم إرسال طلب انضمامك! سنراجعه ونبلغك بالنتيجة عبر بريدك الإلكتروني.');
    }

    /**
     * Only a pending request can be withdrawn; an approved place is removed by an admin.
     */
    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $course = $this->catalog->visible()->where('slug', $slug)->firstOrFail();

        $request->user()->enrollments()
            ->where('course_id', $course->id)
            ->where('status', CourseEnrollment::STATUS_PENDING)
            ->delete();

        return redirect()
            ->to(route('courses.show', $course->slug).'#enroll')
            ->with('enrollment_success', 'تم سحب طلب الانضمام.');
    }
}
