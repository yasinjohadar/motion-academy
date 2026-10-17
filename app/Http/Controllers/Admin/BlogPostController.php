<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\BlogPostStoreRequest;
use App\Http\Requests\Admin\Blog\BlogPostUpdateRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Notifications\Blog\BlogPostPublished;
use App\Services\Storage\AppStorageManager;
use App\Support\NotificationRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(private readonly AppStorageManager $storageManager) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'blog-posts',
            false,
            [
                'admin.blog-posts.edit' => ['togglePublish', 'toggleFeatured', 'deleteImage'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));
        $status = $request->string('status')->toString();
        $categoryId = $request->integer('blog_category_id') ?: null;

        $posts = BlogPost::query()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->when($query !== '', fn ($builder) => $builder->search($query))
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($categoryId, fn ($builder) => $builder->where('blog_category_id', $categoryId))
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $post->toAdminArray(false));

        return Inertia::render('Admin/Blog/Posts/Index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (BlogCategory $category) => $category->only(['id', 'name']))
                ->values()
                ->all(),
            'filters' => [
                'query' => $query,
                'status' => $status,
                'blog_category_id' => $categoryId,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Blog/Posts/Create', [
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(BlogPostStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])));
        $featuredImageUrl = $data['featured_image_url'] ?? null;
        unset($data['tag_ids'], $data['featured_image'], $data['featured_image_url']);

        $slug = filled($data['slug'] ?? null)
            ? BlogCategory::slugify((string) $data['slug'])
            : BlogCategory::slugify((string) $data['title']);

        if ($request->hasFile('featured_image')) {
            $path = $this->storageManager->storeUploadedFile('blog', $request->file('featured_image'), 'posts');
            if ($path !== false) {
                $data['featured_image'] = $path;
            }
        } elseif (filled($featuredImageUrl)) {
            $data['featured_image'] = $featuredImageUrl;
        }

        $post = BlogPost::query()->create([
            ...$data,
            'slug' => BlogPost::uniqueSlug($slug),
            'author_id' => $request->user()->id,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_indexable' => (bool) ($data['is_indexable'] ?? true),
            'is_followable' => (bool) ($data['is_followable'] ?? true),
            'language' => $data['language'] ?? 'ar',
            'robots_meta' => $data['robots_meta'] ?? 'index,follow',
            'priority' => (int) ($data['priority'] ?? 0),
            'order' => (int) ($data['order'] ?? 0),
        ]);

        $post->tags()->sync($tagIds);
        $this->syncRelatedCounts($post->blog_category_id, $tagIds);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'تم إنشاء المقال بنجاح.');
    }

    public function edit(Request $request, BlogPost $blog_post): Response
    {
        $this->authorize('update', $blog_post);

        $blog_post->load(['author:id,name', 'category', 'tags']);

        return Inertia::render('Admin/Blog/Posts/Edit', [
            'post' => $blog_post->toAdminArray(),
            'categories' => $this->categoryOptions(),
            'tags' => $this->tagOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(BlogPostUpdateRequest $request, BlogPost $blog_post): RedirectResponse
    {
        $this->authorize('update', $blog_post);

        $data = $request->validated();
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])));
        $featuredImageUrl = $data['featured_image_url'] ?? null;
        unset($data['tag_ids'], $data['featured_image'], $data['featured_image_url']);

        $oldCategoryId = $blog_post->blog_category_id;
        $oldTagIds = $blog_post->tags()->pluck('blog_tags.id')->map(fn ($id) => (int) $id)->all();

        $slugInput = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : (string) $blog_post->slug;

        if ($request->hasFile('featured_image')) {
            if (filled($blog_post->featured_image) && ! str_starts_with((string) $blog_post->featured_image, 'http')) {
                $this->storageManager->delete('blog', $blog_post->featured_image);
            }

            $path = $this->storageManager->storeUploadedFile('blog', $request->file('featured_image'), 'posts');
            if ($path !== false) {
                $data['featured_image'] = $path;
            }
        } elseif (filled($featuredImageUrl)) {
            if (filled($blog_post->featured_image) && ! str_starts_with((string) $blog_post->featured_image, 'http')) {
                $this->storageManager->delete('blog', $blog_post->featured_image);
            }
            $data['featured_image'] = $featuredImageUrl;
        }

        $blog_post->update([
            ...$data,
            'slug' => BlogPost::uniqueSlug(BlogCategory::slugify($slugInput), $blog_post->id),
            'is_featured' => (bool) ($data['is_featured'] ?? $blog_post->is_featured),
            'is_indexable' => (bool) ($data['is_indexable'] ?? $blog_post->is_indexable),
            'is_followable' => (bool) ($data['is_followable'] ?? $blog_post->is_followable),
            'priority' => (int) ($data['priority'] ?? $blog_post->priority),
            'order' => (int) ($data['order'] ?? $blog_post->order),
        ]);

        $blog_post->tags()->sync($tagIds);

        $categoryIds = array_values(array_unique(array_filter([
            $oldCategoryId,
            $blog_post->blog_category_id,
        ])));
        foreach ($categoryIds as $categoryId) {
            $this->syncCategoryCount((int) $categoryId);
        }

        $this->syncTagCounts(array_values(array_unique([...$oldTagIds, ...$tagIds])));

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'تم تحديث المقال بنجاح.');
    }

    public function destroy(BlogPost $blog_post): RedirectResponse
    {
        $this->authorize('delete', $blog_post);

        $categoryId = $blog_post->blog_category_id;
        $tagIds = $blog_post->tags()->pluck('blog_tags.id')->map(fn ($id) => (int) $id)->all();

        if (filled($blog_post->featured_image) && ! str_starts_with((string) $blog_post->featured_image, 'http')) {
            $this->storageManager->delete('blog', $blog_post->featured_image);
        }

        $blog_post->tags()->detach();
        $blog_post->delete();

        if ($categoryId) {
            $this->syncCategoryCount((int) $categoryId);
        }
        $this->syncTagCounts($tagIds);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'تم حذف المقال بنجاح.');
    }

    public function togglePublish(BlogPost $blog_post): RedirectResponse
    {
        $this->authorize('update', $blog_post);

        if ($blog_post->status === 'published') {
            $blog_post->update(['status' => 'draft']);
            $message = 'تم تحويل المقال إلى مسودة.';
        } else {
            $blog_post->update([
                'status' => 'published',
                'published_at' => $blog_post->published_at ?? now(),
            ]);
            $message = 'تم نشر المقال.';

            Notification::send(
                NotificationRecipients::forPermission('admin.blog-posts.list'),
                new BlogPostPublished($blog_post->loadMissing('author'))
            );
        }

        return back()->with('success', $message);
    }

    public function toggleFeatured(BlogPost $blog_post): RedirectResponse
    {
        $this->authorize('update', $blog_post);

        $blog_post->update(['is_featured' => ! $blog_post->is_featured]);

        return back()->with(
            'success',
            $blog_post->is_featured ? 'تم تمييز المقال.' : 'تم إلغاء تمييز المقال.',
        );
    }

    public function deleteImage(BlogPost $blog_post): RedirectResponse
    {
        $this->authorize('update', $blog_post);

        if (! filled($blog_post->featured_image)) {
            return back()->with('error', 'لا توجد صورة لحذفها.');
        }

        if (! str_starts_with((string) $blog_post->featured_image, 'http')) {
            $this->storageManager->delete('blog', $blog_post->featured_image);
        }
        $blog_post->update([
            'featured_image' => null,
            'featured_image_alt' => null,
        ]);

        return back()->with('success', 'تم حذف الصورة البارزة.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryOptions(): array
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogCategory $category) => $category->only(['id', 'name', 'slug', 'parent_id']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tagOptions(): array
    {
        return BlogTag::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogTag $tag) => $tag->only(['id', 'name', 'slug', 'color']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    private function syncRelatedCounts(?int $categoryId, array $tagIds): void
    {
        if ($categoryId) {
            $this->syncCategoryCount($categoryId);
        }

        $this->syncTagCounts($tagIds);
    }

    private function syncCategoryCount(int $categoryId): void
    {
        $category = BlogCategory::query()->find($categoryId);
        if (! $category) {
            return;
        }

        $category->update(['posts_count' => $category->posts()->count()]);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    private function syncTagCounts(array $tagIds): void
    {
        foreach (array_unique($tagIds) as $tagId) {
            $tag = BlogTag::query()->find($tagId);
            if (! $tag) {
                continue;
            }

            $tag->update(['posts_count' => $tag->posts()->count()]);
        }
    }
}
