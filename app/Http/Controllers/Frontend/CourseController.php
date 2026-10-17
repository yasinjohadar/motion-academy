<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\CourseLevel;
use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Services\Frontend\CourseCatalog;
use App\Services\Seo\SeoPage;
use App\Services\Seo\SiteSeo;
use App\Services\Seo\StructuredData;
use App\Support\CourseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The public course catalogue and course pages.
 */
class CourseController extends Controller
{
    public function __construct(private readonly CourseCatalog $catalog) {}

    public function index(Request $request): View
    {
        $filters = $this->catalog->filtersFromRequest($request);
        $visible = $this->catalog->visible();

        $courses = $this->catalog->paginate($filters);

        return view('frontend.pages.courses', [
            'seo' => $this->catalogSeo($filters, $courses),
            'courses' => $courses,
            'filters' => $filters,
            'categories' => $this->catalog->rootCategories(),
            'maxPrice' => $this->catalog->maxPrice(),
            'stats' => [
                'total' => (clone $visible)->count(),
                'categories' => $this->catalog->rootCategories()->count(),
                'lessons' => (int) (clone $visible)->sum('lessons_count'),
                'hours' => (int) ceil((clone $visible)->sum('duration_seconds') / 3600),
                'free' => (clone $visible)->where('is_free', true)->count(),
            ],
            'levels' => collect(CourseLevel::cases())
                ->reject(fn ($level) => $level === CourseLevel::All)
                ->map(fn ($level) => ['value' => $level->value, 'label' => $level->label()])
                ->values(),
        ]);
    }

    /**
     * AJAX for the courses page filters (courses.js) and the navbar live search
     * (main.js, which passes `limit`). Returns rendered cards plus pagination.
     */
    public function search(Request $request): JsonResponse
    {
        $filters = $this->catalog->filtersFromRequest($request);
        $view = $request->input('view') === 'list' ? 'list' : 'grid';

        if ($request->filled('limit')) {
            $courses = $this->catalog->limit($filters, max(1, min((int) $request->input('limit'), 20)));

            return response()->json([
                'html' => $this->renderCards($courses, $view),
                'count' => $courses->count(),
                'pagination' => '',
            ]);
        }

        $courses = $this->catalog->paginate($filters, (int) $request->input('per_page', 9));

        return response()->json([
            'html' => $this->renderCards($courses, $view),
            'count' => $courses->total(),
            'pagination' => view('frontend.partials.courses-pagination', ['courses' => $courses])->render(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
            ],
        ]);
    }

    public function show(string $slug): View
    {
        $course = $this->catalog->visible()
            ->where('slug', $slug)
            ->with(['sections' => fn ($query) => $query->with([
                'lessons' => fn ($lessons) => $lessons->where('is_published', true),
            ])])
            ->firstOrFail();

        // Sections with no published lessons are not shown to visitors.
        $course->setRelation('sections', $course->sections->filter(
            fn ($section) => $section->lessons->isNotEmpty(),
        )->values());

        $related = $this->catalog->visible()
            ->whereKeyNot($course->id)
            ->where('course_category_id', $course->course_category_id)
            ->latest('published_at')
            ->take(3)
            ->get();

        if ($related->count() < 3) {
            $related = $related->concat(
                $this->catalog->visible()
                    ->whereKeyNot([$course->id, ...$related->modelKeys()])
                    ->orderByDesc('is_featured')
                    ->latest('published_at')
                    ->take(3 - $related->count())
                    ->get(),
            );
        }

        return view('frontend.pages.course-detail', [
            'seo' => $this->courseSeo($course),
            'course' => $course,
            'relatedCourses' => $related,
            'previews' => $this->previewPlaylist($course),
            'enrollment' => auth()->user()?->enrollments()->where('course_id', $course->id)->first(),
            'isFull' => $course->isFull(),
            'canLearn' => CourseAccess::canLearn(auth()->user(), $course),
        ]);
    }

    private function courseSeo(Course $course): SeoPage
    {
        $url = route('courses.show', $course->slug);
        $trail = ['الدورات' => route('courses')];
        if ($course->category) {
            $trail[$course->category->name] = route('courses', ['category' => $course->category->slug]);
        }
        $trail[$course->title] = $url;

        return SeoPage::make($course->meta_title ?: $course->title)
            ->description($course->meta_description ?: $course->short_description ?: $course->description)
            ->keywords($course->meta_keywords)
            ->image($course->og_image ?: $course->thumbnailUrl(), $course->title)
            ->canonical($url)
            ->robots($course->is_indexable ?? true)
            ->breadcrumbs($trail)
            ->schema(StructuredData::course($course, app(SiteSeo::class)));
    }

    /**
     * The catalogue is one indexable page per category (plus its pages); a
     * search or any other filter combination is a duplicate of those, so it is
     * kept out of results while its links are still followed.
     *
     * @param  array<string, mixed>  $filters
     * @param  LengthAwarePaginator<int, Course>  $courses
     */
    private function catalogSeo(array $filters, LengthAwarePaginator $courses): SeoPage
    {
        $category = count($filters['categories']) === 1
            ? CourseCategory::query()->where('slug', $filters['categories'][0])->where('is_active', true)->first()
            : null;

        $narrowed = $filters['search'] !== ''
            || count($filters['categories']) > 1
            || $filters['levels'] !== []
            || $filters['price_max'] !== null
            || $filters['pricing'] !== ''
            || $filters['sort'] !== 'popular'
            || (count($filters['categories']) === 1 && ! $category);

        $base = $category ? ['category' => $category->slug] : [];
        $page = $courses->currentPage();
        $pageUrl = fn (int $number) => route('courses', $number > 1 ? [...$base, 'page' => $number] : $base);

        $seo = $category
            ? SeoPage::make($category->meta_title ?: 'دورات '.$category->name)
                ->description($category->meta_description ?: $category->description ?: 'كل دورات '.$category->name.' في مكان واحد.')
                ->keywords($category->meta_keywords)
                ->image($category->og_image ?: $category->imageUrl(), $category->name)
                ->breadcrumbs(['الدورات' => route('courses'), $category->name => $pageUrl(1)])
            : SeoPage::make('الدورات')
                ->description('تصفّح كل الدورات: مجانية ومدفوعة، لكل المستويات، مع دروس فيديو عملية خطوة بخطوة.')
                ->breadcrumbs(['الدورات' => route('courses')]);

        if ($page > 1) {
            $seo->title(($seo->title ?? 'الدورات').' — صفحة '.$page);
        }

        if ($narrowed) {
            return $seo->canonical(route('courses'))->noindex();
        }

        if ($category && ! ($category->is_indexable ?? true)) {
            $seo->noindex();
        }

        return $seo
            ->canonical($pageUrl($page))
            ->pagination(
                $page > 1 ? $pageUrl($page - 1) : null,
                $courses->hasMorePages() ? $pageUrl($page + 1) : null,
            )
            ->schema(StructuredData::itemList(
                collect($courses->items())->map(fn (Course $course) => [
                    'name' => $course->title,
                    'url' => route('courses.show', $course->slug),
                ]),
                $pageUrl($page),
                ($page - 1) * $courses->perPage(),
            ));
    }

    /**
     * What a visitor may watch for free: the intro video, then every published
     * preview lesson that has a playable video. `kind` tells the player whether
     * to load an embed (YouTube/Vimeo) or a <video> source.
     *
     * @return array<int, array{id: string, title: string, kind: string, src: string, duration: string|null, label: string}>
     */
    private function previewPlaylist(Course $course): array
    {
        $playlist = [];

        if ($course->intro_video_source !== null) {
            $embed = $course->intro_video_source->embedUrl($course->intro_video);
            $src = $embed ?? $course->introVideoUrl();
            if ($src) {
                $playlist[] = [
                    'id' => 'intro',
                    'title' => 'الفيديو التعريفي للدورة',
                    'kind' => $embed ? 'embed' : 'file',
                    'src' => $src,
                    'duration' => null,
                    'label' => 'تعريفي',
                ];
            }
        }

        foreach ($course->sections as $section) {
            foreach ($section->lessons as $lesson) {
                if (! $lesson->is_preview || $lesson->type !== LessonType::Video) {
                    continue;
                }
                $embed = $lesson->video_source?->embedUrl($lesson->video);
                $src = $embed ?? $lesson->videoUrl();
                if (! $src) {
                    continue;
                }
                $playlist[] = [
                    'id' => 'lesson-'.$lesson->id,
                    'title' => $lesson->title,
                    'kind' => $embed ? 'embed' : 'file',
                    'src' => $src,
                    'duration' => $lesson->formattedDuration(),
                    'label' => 'درس مجاني',
                ];
            }
        }

        return $playlist;
    }

    /**
     * @param  LengthAwarePaginator<int, Course>|Collection<int, Course>  $courses
     */
    private function renderCards(LengthAwarePaginator|Collection $courses, string $view): string
    {
        if ($courses->isEmpty()) {
            return view('frontend.partials.courses-empty')->render();
        }

        $partial = $view === 'list' ? 'frontend.partials.course-list-card' : 'frontend.partials.course-card-grid';

        $items = $courses instanceof LengthAwarePaginator ? $courses->items() : $courses->all();

        return collect($items)
            ->map(fn (Course $course) => view($partial, ['course' => $course])->render())
            ->implode('');
    }
}
