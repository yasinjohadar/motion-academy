<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\BlogTagStoreRequest;
use App\Http\Requests\Admin\Blog\BlogTagUpdateRequest;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class BlogTagController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'blog-tags',
            false,
            [
                'admin.blog-tags.edit' => ['updateCounts'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $tags = BlogTag::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BlogTag $tag) => $tag->toAdminArray());

        return Inertia::render('Admin/Blog/Tags/Index', [
            'tags' => $tags,
            'filters' => [
                'query' => $query,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Blog/Tags/Create', [
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(BlogTagStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = filled($data['slug'] ?? null)
            ? BlogCategory::slugify((string) $data['slug'])
            : BlogCategory::slugify((string) $data['name']);

        BlogTag::query()->create([
            ...$data,
            'slug' => BlogTag::uniqueSlug($slug),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_indexable' => (bool) ($data['is_indexable'] ?? true),
            'order' => (int) ($data['order'] ?? 0),
            'posts_count' => 0,
        ]);

        return redirect()
            ->route('admin.blog-tags.index')
            ->with('success', 'تم إنشاء الوسم بنجاح.');
    }

    public function edit(Request $request, BlogTag $blog_tag): Response
    {
        return Inertia::render('Admin/Blog/Tags/Edit', [
            'tag' => $blog_tag->toAdminArray(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(BlogTagUpdateRequest $request, BlogTag $blog_tag): RedirectResponse
    {
        $data = $request->validated();

        $slugInput = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : (string) $blog_tag->slug;

        $blog_tag->update([
            ...$data,
            'slug' => BlogTag::uniqueSlug(BlogCategory::slugify($slugInput), $blog_tag->id),
            'is_active' => (bool) ($data['is_active'] ?? $blog_tag->is_active),
            'is_indexable' => (bool) ($data['is_indexable'] ?? $blog_tag->is_indexable),
            'order' => (int) ($data['order'] ?? $blog_tag->order),
        ]);

        return redirect()
            ->route('admin.blog-tags.index')
            ->with('success', 'تم تحديث الوسم بنجاح.');
    }

    public function destroy(BlogTag $blog_tag): RedirectResponse
    {
        $blog_tag->posts()->detach();
        $blog_tag->delete();

        return redirect()
            ->route('admin.blog-tags.index')
            ->with('success', 'تم حذف الوسم بنجاح.');
    }

    public function updateCounts(): RedirectResponse
    {
        BlogTag::query()->each(function (BlogTag $tag): void {
            $tag->update(['posts_count' => $tag->posts()->count()]);
        });

        return back()->with('success', 'تم تحديث عدد مقالات الوسوم.');
    }
}
