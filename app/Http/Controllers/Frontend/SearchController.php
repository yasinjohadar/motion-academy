<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Services\Frontend\CourseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Navbar live search (assets/js/search.js).
 *
 * With a query it returns matching courses, categories and articles. Without one it
 * returns what the empty dropdown shows: featured courses, categories and the
 * phrases the placeholder cycles through.
 */
class SearchController extends Controller
{
    public function __construct(private readonly CourseCatalog $catalog) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = Str::limit(trim((string) $request->input('q', '')), 80, '');

        if ($query === '') {
            return response()->json($this->suggestions());
        }

        $courses = $this->catalog->visible()->search($query)
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(5)
            ->get();

        $categories = CourseCategory::query()->active()
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('order')
            ->limit(4)
            ->get();

        $posts = BlogPost::query()->published()->search($query)
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'reading_time']);

        return response()->json([
            'query' => $query,
            'courses' => $courses->map(fn (Course $course) => $this->courseItem($course))->values(),
            'categories' => $categories->map(fn (CourseCategory $category) => $this->categoryItem($category))->values(),
            'posts' => $posts->map(fn (BlogPost $post) => [
                'title' => $post->title,
                'url' => route('blog.show', $post->slug),
                'meta' => $post->reading_time ? $post->reading_time.' د قراءة' : 'مقال',
            ])->values(),
            'total' => $courses->count() + $categories->count() + $posts->count(),
            'all_url' => route('courses', ['search' => $query]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestions(): array
    {
        $courses = $this->catalog->visible()
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(4)
            ->get();

        $categories = $this->catalog->rootCategories(6);

        return [
            'query' => '',
            'courses' => $courses->map(fn (Course $course) => $this->courseItem($course))->values(),
            'categories' => $categories->map(fn (CourseCategory $category) => $this->categoryItem($category))->values(),
            'posts' => [],
            'phrases' => $categories->pluck('name')
                ->merge($courses->pluck('title'))
                ->map(fn (string $text) => Str::limit($text, 34))
                ->unique()
                ->take(6)
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseItem(Course $course): array
    {
        return [
            'title' => $course->title,
            'url' => route('courses.show', $course->slug),
            'category' => $course->category?->name,
            'color' => $course->category?->color ?? '#02ad8f',
            'icon' => $course->category?->publicIcon() ?? 'fas fa-play',
            'thumbnail' => $course->thumbnailUrl(),
            'price' => $course->formattedPrice(),
            'lessons' => $course->lessons_count,
            'is_free' => $course->is_free,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryItem(CourseCategory $category): array
    {
        return [
            'name' => $category->name,
            'url' => route('courses', ['categories' => [$category->slug]]),
            'icon' => $category->publicIcon(),
            'color' => $category->color ?? '#02ad8f',
            'count' => $category->getAttributes()['courses_count'] ?? null,
        ];
    }
}
