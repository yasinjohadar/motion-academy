<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Frontend\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A course's standalone advertising page: no site header or footer, one
 * inline stylesheet, and YouTube players that load only when tapped.
 *
 * Visitors see it only when the course is public and the page is published.
 * Someone who may edit the course can open `?preview=1` at any time.
 */
class CourseLandingController extends Controller
{
    public function __construct(private readonly CourseCatalog $catalog) {}

    public function show(Request $request, string $slug): View
    {
        $isPreview = $request->boolean('preview') && $request->user() !== null;

        $course = $isPreview
            ? Course::query()->where('slug', $slug)->with(['category', 'instructor:id,name', 'landing'])->first()
            : $this->catalog->visible()->where('slug', $slug)->with('landing')->first();

        $landing = $course?->landing;

        abort_if($course === null || $landing === null, 404);

        if ($isPreview) {
            abort_unless($request->user()->can('update', $course), 404);
        } else {
            abort_unless($landing->is_published, 404);
        }

        $landing->setRelation('course', $course);

        return view('frontend.landing.course', [
            'course' => $course,
            'landing' => $landing,
            'isPreview' => $isPreview,
            'meta' => [
                'title' => $landing->meta_title ?: $course->title,
                'description' => $landing->meta_description
                    ?: $landing->tagline
                    ?: Str::limit(trim(html_entity_decode(strip_tags((string) ($course->short_description ?: $course->subtitle)))), 160),
                'image' => $course->thumbnailUrl(),
                'url' => route('courses.landing', $course->slug),
                'indexable' => ! $isPreview && $landing->allow_indexing && ($course->is_indexable ?? true),
            ],
        ]);
    }
}
