<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\CourseLandingRequest;
use App\Models\Course;
use App\Models\CourseLanding;
use App\Support\SocialPlatforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Edits a course's standalone landing page. Access follows the course itself:
 * whoever may edit the course may edit its landing page.
 */
class CourseLandingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.courses.edit'),
        ];
    }

    public function edit(Request $request, Course $course): Response
    {
        $this->authorize('update', $course);

        $landing = $course->landing ?? new CourseLanding([
            // A new page starts from the course's own outcomes; the admin can edit them freely.
            'goals' => $course->learning_outcomes ?? [],
        ]);

        return Inertia::render('Admin/Courses/Landing', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'subtitle' => $course->subtitle,
                'thumbnail_url' => $course->thumbnailUrl(),
                'price' => $course->formattedPrice(),
                'is_published' => $course->status === CourseStatus::Published,
                'landing_url' => route('courses.landing', $course->slug),
                'preview_url' => route('courses.landing', ['slug' => $course->slug, 'preview' => 1]),
            ],
            'landing' => [
                ...$landing->toAdminArray(),
                'exists' => $landing->exists,
            ],
            'platforms' => SocialPlatforms::options(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(CourseLandingRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $request->validated();

        $course->landing()->updateOrCreate([], [
            ...$data,
            'videos' => array_map(fn (array $video) => [
                'title' => $video['title'] ?? null,
                'url' => $video['url'],
            ], $data['videos'] ?? []),
            'features' => array_map(fn (array $feature) => [
                'title' => $feature['title'],
                'text' => $feature['text'] ?? null,
            ], $data['features'] ?? []),
            'testimonials' => array_map(fn (array $item) => [
                'name' => $item['name'],
                'role' => $item['role'] ?? null,
                'text' => $item['text'] ?? null,
                'video_url' => $item['video_url'] ?? null,
            ], $data['testimonials'] ?? []),
            'faqs' => array_map(fn (array $faq) => [
                'question' => $faq['question'],
                'answer' => $faq['answer'],
            ], $data['faqs'] ?? []),
            'social_links' => array_map(fn (array $link) => [
                'platform' => $link['platform'],
                'url' => $link['url'],
            ], $data['social_links'] ?? []),
        ]);

        return back()->with('success', 'تم حفظ صفحة الهبوط.');
    }
}
