<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\HeroSlide;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Services\Frontend\CourseCatalog;
use App\Services\Frontend\HeroSliderSettings;
use App\Services\Frontend\HomeSections;
use App\Services\Frontend\TeamSettings;
use App\Services\Frontend\TestimonialSettings;
use App\Services\Seo\SeoPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * Public home page. Every section comes from the database, and each one can be
 * hidden from the panel (HomeSections).
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly CourseCatalog $catalog,
        private readonly HeroSliderSettings $heroSettings,
        private readonly HomeSections $sections,
        private readonly TestimonialSettings $testimonialSettings,
        private readonly TeamSettings $teamSettings,
    ) {}

    /**
     * Hidden sections skip their queries; their variables are left empty.
     */
    public function index(): View
    {
        $showsHero = $this->sections->shows('hero');

        $featured = $showsHero || $this->sections->shows('courses')
            ? $this->catalog->visible()
                ->orderByDesc('is_featured')
                ->latest('published_at')
                ->take(9)
                ->get()
            : new Collection;

        $heroSettings = $this->heroSettings->all();
        $testimonialSettings = $this->testimonialSettings->all();

        $testimonials = $this->sections->shows('testimonials')
            ? Testimonial::query()->forHomePage($testimonialSettings)->get()
            : new Collection;

        if ($testimonials->isEmpty()) {
            $this->sections->suppress('testimonials');
        }

        $teamSettings = $this->teamSettings->all();

        $teamMembers = $this->sections->shows('team')
            ? TeamMember::query()->forHomePage($teamSettings)->get()
            : new Collection;

        if ($teamMembers->isEmpty()) {
            $this->sections->suppress('team');
        }

        return view('frontend.pages.home', [
            // No title: the home page uses the site-wide SEO title as-is.
            'seo' => SeoPage::make()->canonical(route('home')),
            'homeSections' => $this->sections,
            'homeCategories' => $this->sections->shows('categories')
                ? $this->catalog->rootCategories(limit: 6)
                : new Collection,
            'homeCourses' => $featured->take(6),
            'sliderCourses' => $featured,
            'heroSlides' => $showsHero
                ? HeroSlide::query()
                    ->showing()
                    ->when($heroSettings['max_slides'] > 0, fn ($q) => $q->take($heroSettings['max_slides']))
                    ->get()
                : new Collection,
            'heroSettings' => $heroSettings,
            'testimonials' => $testimonials,
            'testimonialSettings' => $testimonialSettings,
            'teamMembers' => $teamMembers,
            'teamSettings' => $teamSettings,
            'blogPosts' => $this->sections->shows('blog')
                ? BlogPost::query()
                    ->published()
                    ->with(['category', 'author:id,name'])
                    ->latest('published_at')
                    ->take(6)
                    ->get()
                : new Collection,
            'stats' => $showsHero ? $this->stats() : [],
        ]);
    }

    /**
     * @return array{courses: int, instructors: int, lessons: int, hours: int}
     */
    private function stats(): array
    {
        $visible = $this->catalog->visible();

        return [
            'courses' => (clone $visible)->count(),
            'instructors' => (clone $visible)->whereNotNull('instructor_id')->distinct()->count('instructor_id'),
            'lessons' => (int) (clone $visible)->sum('lessons_count'),
            'hours' => (int) ceil((clone $visible)->sum('duration_seconds') / 3600),
        ];
    }
}
