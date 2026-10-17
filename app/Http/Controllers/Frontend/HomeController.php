<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Frontend\CourseCatalog;
use App\Services\Seo\SeoPage;
use Illuminate\View\View;

/**
 * Public home page. The hero, team and testimonials are design content; the
 * stats, categories, course sections and blog strip come from the database.
 */
class HomeController extends Controller
{
    public function __construct(private readonly CourseCatalog $catalog) {}

    public function index(): View
    {
        $featured = $this->catalog->visible()
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->take(9)
            ->get();

        $visible = $this->catalog->visible();

        return view('frontend.pages.home', [
            // No title: the home page uses the site-wide SEO title as-is.
            'seo' => SeoPage::make()->canonical(route('home')),
            'homeCategories' => $this->catalog->rootCategories(limit: 6),
            'homeCourses' => $featured->take(6),
            'sliderCourses' => $featured,
            'blogPosts' => BlogPost::query()
                ->published()
                ->with(['category', 'author:id,name'])
                ->latest('published_at')
                ->take(6)
                ->get(),
            'stats' => [
                'courses' => (clone $visible)->count(),
                'instructors' => (clone $visible)->whereNotNull('instructor_id')->distinct()->count('instructor_id'),
                'lessons' => (int) (clone $visible)->sum('lessons_count'),
                'hours' => (int) ceil((clone $visible)->sum('duration_seconds') / 3600),
            ],
        ]);
    }
}
