<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Services\Frontend\CourseCatalog;
use App\Services\Seo\SiteSeo;
use Illuminate\Http\Response;

/**
 * /sitemap.xml and /robots.txt, generated from the live catalogue and blog so
 * they never drift from what is actually published.
 */
class SeoController extends Controller
{
    /**
     * Paths that are never landing pages: accounts, checkout, the learning area,
     * the admin panel, and JSON endpoints.
     */
    public const PRIVATE_PATHS = [
        '/admin',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/verify-email',
        '/confirm-password',
        '/cart',
        '/checkout',
        '/my-courses',
        '/dashboard',
        '/learn/',
        '/lessons/',
        '/newsletter/',
        '/search/',
        '/courses/search',
        '/blog/search',
    ];

    public function __construct(
        private readonly SiteSeo $site,
        private readonly CourseCatalog $catalog,
    ) {}

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (! $this->site->allowsIndexing()) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = 'Allow: /';
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ...$this->staticPages(),
            ...$this->courseCategories(),
            ...$this->courses(),
            ...$this->blogCategories(),
            ...$this->blogTags(),
            ...$this->blogPosts(),
        ];

        return response()
            ->view('frontend.seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staticPages(): array
    {
        $latestCourse = Course::query()->published()->max('updated_at');
        $latestPost = BlogPost::query()->published()->max('updated_at');

        return [
            ['loc' => route('home'), 'lastmod' => max($latestCourse, $latestPost), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('courses'), 'lastmod' => $latestCourse, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('categories'), 'lastmod' => $latestCourse, 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('blog.index'), 'lastmod' => $latestPost, 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.4'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function courseCategories(): array
    {
        return CourseCategory::query()
            ->where('is_active', true)
            ->where('is_indexable', true)
            ->whereIn('id', Course::query()->published()->select('course_category_id'))
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (CourseCategory $category) => [
                'loc' => route('courses', ['category' => $category->slug]),
                'lastmod' => $category->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function courses(): array
    {
        return $this->catalog->visible()
            ->where('is_indexable', true)
            ->latest('updated_at')
            ->get()
            ->map(fn (Course $course) => [
                'loc' => route('courses.show', $course->slug),
                'lastmod' => $course->updated_at,
                'changefreq' => 'weekly',
                'priority' => $course->is_featured ? '0.9' : '0.8',
                'image' => $this->site->absolute($course->og_image ?: $course->thumbnailUrl()),
                'image_title' => $course->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blogCategories(): array
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->where('is_indexable', true)
            ->whereHas('publishedPosts')
            ->get()
            ->reject(fn (BlogCategory $category) => str_contains(strtolower((string) $category->robots_meta), 'noindex'))
            ->map(fn (BlogCategory $category) => [
                'loc' => $category->canonical_url ?: route('blog.category', $category->slug),
                'lastmod' => $category->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blogTags(): array
    {
        return BlogTag::query()
            ->where('is_active', true)
            ->where('is_indexable', true)
            ->whereHas('publishedPosts')
            ->get()
            ->map(fn (BlogTag $tag) => [
                'loc' => $tag->canonical_url ?: route('blog.tag', $tag->slug),
                'lastmod' => $tag->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.4',
            ])
            ->values()
            ->all();
    }

    /**
     * Posts that ask not to be indexed, or whose canonical points elsewhere,
     * do not belong in the sitemap.
     *
     * @return list<array<string, mixed>>
     */
    private function blogPosts(): array
    {
        return BlogPost::query()
            ->published()
            ->where('is_indexable', true)
            ->latest('published_at')
            ->get()
            ->reject(fn (BlogPost $post) => str_contains(strtolower((string) $post->robots_meta), 'noindex')
                || (filled($post->canonical_url) && $post->canonical_url !== route('blog.show', $post->slug)))
            ->map(fn (BlogPost $post) => [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updated_at,
                'changefreq' => 'monthly',
                'priority' => $post->is_featured ? '0.8' : '0.6',
                'image' => $this->site->absolute($post->og_image ?: $post->featuredImageUrl()),
                'image_title' => $post->title,
            ])
            ->values()
            ->all();
    }
}
