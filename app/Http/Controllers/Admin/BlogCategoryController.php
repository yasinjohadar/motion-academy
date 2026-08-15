<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\BlogCategoryStoreRequest;
use App\Http\Requests\Admin\Blog\BlogCategoryUpdateRequest;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class BlogCategoryController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'blog-categories',
            false,
            [
                'admin.blog-categories.edit' => ['toggleActive'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $categories = BlogCategory::query()
            ->with('parent:id,name')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BlogCategory $category) => $category->toAdminArray());

        return Inertia::render('Admin/Blog/Categories/Index', [
            'categories' => $categories,
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
        return Inertia::render('Admin/Blog/Categories/Create', [
            'parents' => BlogCategory::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id'])
                ->map(fn (BlogCategory $category) => $category->only(['id', 'name', 'parent_id']))
                ->values()
                ->all(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(BlogCategoryStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = filled($data['slug'] ?? null)
            ? BlogCategory::slugify((string) $data['slug'])
            : BlogCategory::slugify((string) $data['name']);

        BlogCategory::query()->create([
            ...$data,
            'slug' => BlogCategory::uniqueSlug($slug),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_indexable' => (bool) ($data['is_indexable'] ?? true),
            'order' => (int) ($data['order'] ?? 0),
            'robots_meta' => $data['robots_meta'] ?? 'index,follow',
        ]);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'تم إنشاء التصنيف بنجاح.');
    }

    public function edit(Request $request, BlogCategory $blog_category): Response
    {
        return Inertia::render('Admin/Blog/Categories/Edit', [
            'category' => $blog_category->loadMissing('parent:id,name')->toAdminArray(),
            'parents' => BlogCategory::query()
                ->where('id', '!=', $blog_category->id)
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id'])
                ->map(fn (BlogCategory $category) => $category->only(['id', 'name', 'parent_id']))
                ->values()
                ->all(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(BlogCategoryUpdateRequest $request, BlogCategory $blog_category): RedirectResponse
    {
        $data = $request->validated();

        $slugInput = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : (string) $blog_category->slug;

        $blog_category->update([
            ...$data,
            'slug' => BlogCategory::uniqueSlug(BlogCategory::slugify($slugInput), $blog_category->id),
            'is_active' => (bool) ($data['is_active'] ?? $blog_category->is_active),
            'is_featured' => (bool) ($data['is_featured'] ?? $blog_category->is_featured),
            'is_indexable' => (bool) ($data['is_indexable'] ?? $blog_category->is_indexable),
            'order' => (int) ($data['order'] ?? $blog_category->order),
        ]);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'تم تحديث التصنيف بنجاح.');
    }

    public function destroy(BlogCategory $blog_category): RedirectResponse
    {
        if ($blog_category->posts()->exists()) {
            return back()->with('error', 'لا يمكن حذف التصنيف لأنه يحتوي على مقالات.');
        }

        if ($blog_category->children()->exists()) {
            return back()->with('error', 'لا يمكن حذف التصنيف لأنه يحتوي على تصنيفات فرعية.');
        }

        $blog_category->delete();

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'تم حذف التصنيف بنجاح.');
    }

    public function toggleActive(BlogCategory $blog_category): RedirectResponse
    {
        $blog_category->update(['is_active' => ! $blog_category->is_active]);

        return back()->with(
            'success',
            $blog_category->is_active ? 'تم تفعيل التصنيف.' : 'تم تعطيل التصنيف.',
        );
    }
}
