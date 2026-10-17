<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Seo\SeoPage;
use App\Services\Seo\SiteSeo;
use App\Services\Seo\StructuredData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The public blog, rendered with the frontend Blade theme from real posts.
 *
 * Search, category and tag pages share the listing view; each one only narrows
 * the query and sets the heading.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? BlogCategory::query()->where('slug', $request->string('category'))->where('is_active', true)->first()
            : null;

        // "/blog?category=x" shows the same posts as "/blog/category/x": point search engines at the latter.
        $seo = $category
            ? $this->categorySeo($category)
            : SeoPage::make('المدونة')
                ->description('مقالات ودروس في التحريك وصناعة المحتوى البصري: نصائح عملية، أدوات، وتجارب من الميدان.')
                ->breadcrumbs(['المدونة' => route('blog.index')]);

        return $this->listing(
            $request,
            fn (Builder $query) => $query->when($category, fn (Builder $q) => $q->where('blog_category_id', $category->id)),
            ['title' => $category?->name ?? 'المدونة', 'activeCategory' => $category?->slug, 'showFeatured' => $category === null],
            $seo,
            $category ? 'blog.category' : 'blog.index',
            $category ? ['slug' => $category->slug] : [],
        );
    }

    public function search(Request $request): View
    {
        return $this->index($request);
    }

    public function category(Request $request, string $slug): View
    {
        $category = BlogCategory::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->listing(
            $request,
            fn (Builder $query) => $query->where('blog_category_id', $category->id),
            [
                'title' => $category->name,
                'activeCategory' => $category->slug,
                'metaDescription' => $category->meta_description ?: $category->description,
            ],
            $this->categorySeo($category),
            'blog.category',
            ['slug' => $category->slug],
            $category->canonical_url,
        );
    }

    public function tag(Request $request, string $slug): View
    {
        $tag = BlogTag::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $seo = SeoPage::make($tag->meta_title ?: 'مقالات عن '.$tag->name)
            ->description($tag->meta_description ?: $tag->description ?: 'كل مقالات المدونة الموسومة بـ «'.$tag->name.'».')
            ->breadcrumbs(['المدونة' => route('blog.index'), '#'.$tag->name => route('blog.tag', $tag->slug)]);

        if (! ($tag->is_indexable ?? true)) {
            $seo->noindex();
        }

        return $this->listing(
            $request,
            fn (Builder $query) => $query->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->id)),
            [
                'title' => '#'.$tag->name,
                'metaDescription' => $tag->meta_description ?: $tag->description,
            ],
            $seo,
            'blog.tag',
            ['slug' => $tag->slug],
            $tag->canonical_url,
        );
    }

    public function show(string $slug): View
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->with(['author:id,name', 'category', 'tags'])
            ->firstOrFail();

        $post->incrementViews();

        $recentPosts = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->with('category')
            ->latest('published_at')
            ->take(4)
            ->get();

        $prevPost = BlogPost::query()
            ->published()
            ->where('published_at', '>', $post->published_at)
            ->oldest('published_at')
            ->first();

        $nextPost = BlogPost::query()
            ->published()
            ->where('published_at', '<', $post->published_at)
            ->latest('published_at')
            ->first();

        return view('frontend.pages.blog-detail', [
            'post' => $post,
            'recentPosts' => $recentPosts,
            'categories' => $this->categories(withCounts: true),
            'prevPost' => $prevPost,
            'nextPost' => $nextPost,
            'seo' => $this->postSeo($post),
        ]);
    }

    private function postSeo(BlogPost $post): SeoPage
    {
        $url = route('blog.show', $post->slug);
        $robots = strtolower((string) $post->robots_meta);

        $trail = ['المدونة' => route('blog.index')];
        if ($post->category) {
            $trail[$post->category->name] = route('blog.category', $post->category->slug);
        }
        $trail[$post->title] = $url;

        return SeoPage::make($post->meta_title ?: $post->title)
            ->type('article')
            ->description($post->meta_description ?: $post->excerpt ?: $post->content)
            ->keywords($post->meta_keywords ?: $post->focus_keyword)
            ->canonical($post->canonical_url ?: $url)
            ->image($post->og_image ?: $post->featuredImageUrl(), $post->featured_image_alt ?: $post->title)
            ->openGraph($post->og_title, $post->og_description)
            ->twitter($post->twitter_title, $post->twitter_description, $post->twitter_image)
            ->twitterCreator($post->twitter_creator)
            ->robots(
                ($post->is_indexable ?? true) && ! str_contains($robots, 'noindex'),
                ($post->is_followable ?? true) && ! str_contains($robots, 'nofollow'),
            )
            ->article('published_time', $post->published_at?->toIso8601String())
            ->article('modified_time', $post->updated_at?->toIso8601String())
            ->article('author', $post->schema_author_name ?: $post->author?->name)
            ->article('section', $post->category?->name)
            ->article('tag', $post->tags->pluck('name')->all())
            ->breadcrumbs($trail)
            ->schema(StructuredData::blogPosting($post, app(SiteSeo::class)));
    }

    private function categorySeo(BlogCategory $category): SeoPage
    {
        $seo = SeoPage::make($category->meta_title ?: 'مقالات '.$category->name)
            ->description($category->meta_description ?: $category->description ?: 'كل مقالات '.$category->name.' في المدونة.')
            ->keywords($category->meta_keywords)
            ->image($category->og_image, $category->name)
            ->openGraph($category->og_title, $category->og_description)
            ->breadcrumbs(['المدونة' => route('blog.index'), $category->name => route('blog.category', $category->slug)]);

        $robots = strtolower((string) $category->robots_meta);
        if (! ($category->is_indexable ?? true) || str_contains($robots, 'noindex')) {
            $seo->noindex(! str_contains($robots, 'nofollow'));
        }

        return $seo;
    }

    /**
     * @param  callable(Builder<BlogPost>): mixed  $narrow
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $routeParams
     */
    private function listing(
        Request $request,
        callable $narrow,
        array $context,
        SeoPage $seo,
        string $routeName,
        array $routeParams = [],
        ?string $canonicalOverride = null,
    ): View {
        $search = trim((string) $request->string('q'));

        $query = BlogPost::query()
            ->published()
            ->when($search !== '', fn (Builder $q) => $q->search($search))
            ->with(['author:id,name', 'category']);
        $narrow($query);

        $posts = (clone $query)
            ->latest('published_at')
            ->latest('id')
            ->paginate(9)
            ->withQueryString();

        // The highlighted post only belongs on the first page of the unfiltered blog.
        $showFeatured = ($context['showFeatured'] ?? false) && $search === '' && $posts->currentPage() === 1;
        $featuredPost = $showFeatured
            ? BlogPost::query()->published()->featured()->with(['author:id,name', 'category'])->latest('published_at')->first()
                ?? $posts->first()
            : null;

        return view('frontend.pages.blog', [
            ...$context,
            'seo' => $this->listingSeo($seo, $posts, $search, $routeName, $routeParams, $canonicalOverride),
            'posts' => $posts,
            'featuredPost' => $featuredPost,
            'categories' => $this->categories(),
            'search' => $search,
            'totalViews' => (int) BlogPost::query()->published()->sum('views_count'),
        ]);
    }

    /**
     * Search results are not landing pages: kept out of the index, links followed.
     * Paginated pages are each their own canonical, chained with prev/next.
     *
     * @param  LengthAwarePaginator<int, BlogPost>  $posts
     * @param  array<string, string>  $routeParams
     */
    private function listingSeo(
        SeoPage $seo,
        LengthAwarePaginator $posts,
        string $search,
        string $routeName,
        array $routeParams,
        ?string $canonicalOverride,
    ): SeoPage {
        $pageUrl = fn (int $number) => route($routeName, $number > 1 ? [...$routeParams, 'page' => $number] : $routeParams);
        $page = $posts->currentPage();

        if ($search !== '') {
            return $seo->title('نتائج البحث عن «'.$search.'»')->canonical($pageUrl(1))->noindex();
        }

        if ($page > 1) {
            $seo->title($seo->title.' — صفحة '.$page);
        }

        return $seo
            ->canonical($page === 1 && $canonicalOverride ? $canonicalOverride : $pageUrl($page))
            ->pagination(
                $page > 1 ? $pageUrl($page - 1) : null,
                $posts->hasMorePages() ? $pageUrl($page + 1) : null,
            )
            ->schema(StructuredData::itemList(
                collect($posts->items())->map(fn (BlogPost $post) => [
                    'name' => $post->title,
                    'url' => route('blog.show', $post->slug),
                ]),
                $pageUrl($page),
                ($page - 1) * $posts->perPage(),
            ));
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categories(bool $withCounts = false): Collection
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->when($withCounts, fn (Builder $q) => $q->withCount('publishedPosts'))
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }
}
