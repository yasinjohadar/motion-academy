<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SitePageStoreRequest;
use App\Http\Requests\Admin\SitePageUpdateRequest;
use App\Models\SitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SitePageController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'site-pages',
            false,
            [
                'admin.site-pages.edit' => ['togglePublish'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));
        $status = $request->string('status')->toString();

        $pages = SitePage::query()
            ->with('creator:id,name')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('excerpt', 'like', "%{$query}%");
                });
            })
            ->when($status === 'published', fn ($q) => $q->where('is_published', true))
            ->when($status === 'draft', fn ($q) => $q->where('is_published', false))
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SitePage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'is_published' => $page->is_published,
                'sort_order' => $page->sort_order,
                'creator' => $page->creator?->name,
                'updated_at' => optional($page->updated_at)?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/SitePages/Index', [
            'pages' => $pages,
            'filters' => [
                'query' => $query,
                'status' => $status,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SitePages/Create');
    }

    public function store(SitePageStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slug = $data['slug'] ?? null;

        SitePage::create([
            'title' => $data['title'],
            'slug' => $slug
                ? SitePage::uniqueSlug(Str::slug($slug) ?: 'page')
                : SitePage::uniqueSlug(Str::slug($data['title']) ?: 'page'),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.site-pages.index')
            ->with('success', 'تم إنشاء الصفحة بنجاح.');
    }

    public function edit(SitePage $site_page): Response
    {
        $this->authorize('update', $site_page);

        return Inertia::render('Admin/SitePages/Edit', [
            'page' => $site_page->loadMissing('creator:id,name')->toAdminArray(),
        ]);
    }

    public function update(SitePageUpdateRequest $request, SitePage $site_page): RedirectResponse
    {
        $this->authorize('update', $site_page);

        $data = $request->validated();
        $slugInput = $data['slug'] ?? $site_page->slug;

        $site_page->update([
            'title' => $data['title'],
            'slug' => SitePage::uniqueSlug(Str::slug((string) $slugInput) ?: 'page', $site_page->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.site-pages.index')
            ->with('success', 'تم تحديث الصفحة بنجاح.');
    }

    public function destroy(SitePage $site_page): RedirectResponse
    {
        $this->authorize('delete', $site_page);

        $site_page->delete();

        return redirect()
            ->route('admin.site-pages.index')
            ->with('success', 'تم حذف الصفحة.');
    }

    public function togglePublish(SitePage $site_page): RedirectResponse
    {
        $this->authorize('update', $site_page);

        $site_page->update(['is_published' => ! $site_page->is_published]);

        return back()->with(
            'success',
            $site_page->is_published ? 'تم نشر الصفحة.' : 'تم تحويل الصفحة إلى مسودة.'
        );
    }
}
