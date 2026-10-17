<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\CourseCategoryReorderRequest;
use App\Http\Requests\Admin\Course\CourseCategoryStoreRequest;
use App\Http\Requests\Admin\Course\CourseCategoryUpdateRequest;
use App\Models\CourseCategory;
use App\Services\Storage\AppStorageManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourseCategoryController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(private readonly AppStorageManager $storageManager) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'course-categories',
            false,
            [
                'edit' => ['toggleActive', 'toggleFeatured', 'reorder'],
                'admin.course-categories.restore' => ['trash', 'restore', 'forceDelete'],
            ],
        );
    }

    /**
     * Without filters the page shows the whole two-level tree, which is what drag
     * reordering works on. Any search or filter switches to a flat paginated list.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'query' => trim((string) $request->string('query')),
            'status' => in_array($request->string('status')->value(), ['active', 'inactive'], true)
                ? $request->string('status')->value() : '',
            'featured' => in_array($request->string('featured')->value(), ['1', '0'], true)
                ? $request->string('featured')->value() : '',
            'parent' => (string) $request->string('parent'),
        ];

        $isFiltered = collect($filters)->contains(fn (string $value) => $value !== '');

        $tree = null;
        $categories = null;

        if ($isFiltered) {
            $categories = $this->filteredQuery($filters)
                ->with('parent:id,name')
                ->withCount(['children', 'courses'])
                ->orderBy('order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (CourseCategory $category) => $category->toAdminArray());
        } else {
            $tree = CourseCategory::query()
                ->whereNull('parent_id')
                ->withCount(['children', 'courses'])
                ->with(['children' => fn ($query) => $query->withCount(['children', 'courses'])])
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->map(fn (CourseCategory $root) => [
                    ...$root->toAdminArray(),
                    'children' => $root->children
                        ->map(fn (CourseCategory $child) => $child->toAdminArray())
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Courses/Categories/Index', [
            'tree' => $tree,
            'categories' => $categories,
            'roots' => $this->rootOptions(),
            'stats' => [
                'total' => CourseCategory::query()->count(),
                'roots' => CourseCategory::query()->whereNull('parent_id')->count(),
                'active' => CourseCategory::query()->where('is_active', true)->count(),
                'featured' => CourseCategory::query()->where('is_featured', true)->count(),
                'trashed' => CourseCategory::onlyTrashed()->count(),
            ],
            'filters' => $filters,
            'flash' => $this->flash($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Courses/Categories/Create', [
            'roots' => $this->rootOptions(),
            'defaultParentId' => $request->integer('parent') ?: null,
            'flash' => $this->flash($request),
        ]);
    }

    public function store(CourseCategoryStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $image = $this->resolveImage($request);
        unset($data['image'], $data['image_url'], $data['remove_image']);

        $slug = CourseCategory::slugify((string) (filled($data['slug'] ?? null) ? $data['slug'] : $data['name']));
        $parentId = $data['parent_id'] ?? null;

        CourseCategory::query()->create([
            ...$data,
            'image' => $image ?: null,
            'slug' => CourseCategory::uniqueSlug($slug),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'order' => isset($data['order']) ? (int) $data['order'] : $this->nextOrder($parentId),
        ]);

        return redirect()
            ->route('admin.course-categories.index')
            ->with('success', 'تم إنشاء التصنيف بنجاح.');
    }

    public function edit(Request $request, CourseCategory $course_category): Response
    {
        return Inertia::render('Admin/Courses/Categories/Edit', [
            'category' => [
                ...$course_category->loadCount('children')->toAdminArray(),
                'has_children' => $course_category->children_count > 0,
            ],
            'roots' => $this->rootOptions($course_category->id),
            'flash' => $this->flash($request),
        ]);
    }

    public function update(CourseCategoryUpdateRequest $request, CourseCategory $course_category): RedirectResponse
    {
        $data = $request->validated();
        $image = $this->resolveImage($request, $course_category);
        unset($data['image'], $data['image_url'], $data['remove_image']);

        $slugInput = filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $course_category->slug;

        $course_category->update([
            ...$data,
            ...($image !== false ? ['image' => $image] : []),
            'slug' => CourseCategory::uniqueSlug(CourseCategory::slugify($slugInput), $course_category->id),
            'is_active' => (bool) ($data['is_active'] ?? $course_category->is_active),
            'is_featured' => (bool) ($data['is_featured'] ?? $course_category->is_featured),
            'order' => (int) ($data['order'] ?? $course_category->order),
        ]);

        return redirect()
            ->route('admin.course-categories.index')
            ->with('success', 'تم تحديث التصنيف بنجاح.');
    }

    public function destroy(CourseCategory $course_category): RedirectResponse
    {
        if ($course_category->children()->exists()) {
            return back()->with('error', 'لا يمكن حذف التصنيف لأنه يحتوي على تصنيفات فرعية. انقلها أو احذفها أولاً.');
        }

        if ($course_category->courses()->exists()) {
            return back()->with('error', 'لا يمكن حذف التصنيف لأنه يحتوي على دورات. انقلها إلى تصنيف آخر أولاً.');
        }

        $course_category->delete();

        return back()->with('success', 'تم نقل التصنيف إلى سلة المحذوفات.');
    }

    public function toggleActive(CourseCategory $course_category): RedirectResponse
    {
        $course_category->update(['is_active' => ! $course_category->is_active]);

        return back()->with('success', $course_category->is_active ? 'تم تفعيل التصنيف.' : 'تم تعطيل التصنيف.');
    }

    public function toggleFeatured(CourseCategory $course_category): RedirectResponse
    {
        $course_category->update(['is_featured' => ! $course_category->is_featured]);

        return back()->with(
            'success',
            $course_category->is_featured ? 'تم تمييز التصنيف.' : 'تم إلغاء تمييز التصنيف.',
        );
    }

    public function reorder(CourseCategoryReorderRequest $request): RedirectResponse
    {
        /** @var array<int, int> $ids */
        $ids = array_map('intval', $request->validated('ids'));

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                CourseCategory::query()->whereKey($id)->update(['order' => $position + 1]);
            }
        });

        return back()->with('success', 'تم حفظ الترتيب الجديد.');
    }

    public function trash(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $categories = CourseCategory::onlyTrashed()
            ->with(['parent' => fn ($builder) => $builder->withTrashed()->select('id', 'name', 'deleted_at')])
            ->when($query !== '', fn (Builder $builder) => $builder->where(function (Builder $inner) use ($query) {
                $inner->where('name', 'like', "%{$query}%")->orWhere('slug', 'like', "%{$query}%");
            }))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CourseCategory $category) => [
                ...$category->toAdminArray(),
                'parent_trashed' => (bool) $category->parent?->trashed(),
            ]);

        return Inertia::render('Admin/Courses/Categories/Trash', [
            'categories' => $categories,
            'filters' => ['query' => $query],
            'flash' => $this->flash($request),
        ]);
    }

    public function restore(CourseCategory $course_category): RedirectResponse
    {
        if (! $course_category->trashed()) {
            return back()->with('error', 'هذا التصنيف غير موجود في سلة المحذوفات.');
        }

        $parent = $course_category->parent_id
            ? CourseCategory::withTrashed()->find($course_category->parent_id)
            : null;

        if ($parent?->trashed()) {
            return back()->with('error', "استرجع التصنيف الأب «{$parent->name}» أولاً.");
        }

        $course_category->restore();

        return back()->with('success', 'تم استرجاع التصنيف بنجاح.');
    }

    public function forceDelete(CourseCategory $course_category): RedirectResponse
    {
        if (! $course_category->trashed()) {
            return back()->with('error', 'يجب نقل التصنيف إلى سلة المحذوفات قبل حذفه نهائياً.');
        }

        // Trashed courses still reference the category (the FK restricts the delete).
        if ($course_category->courses()->withTrashed()->exists()) {
            return back()->with('error', 'لا يمكن حذف التصنيف نهائياً لأنه مرتبط بدورات (بعضها قد يكون في سلة المحذوفات).');
        }

        DB::transaction(function () use ($course_category): void {
            CourseCategory::withTrashed()
                ->where('parent_id', $course_category->id)
                ->update(['parent_id' => null]);

            $course_category->forceDelete();
        });

        if ($course_category->hasUploadedImage()) {
            $this->storageManager->delete(CourseCategory::STORAGE_DISK, $course_category->image);
        }

        return back()->with('success', 'تم حذف التصنيف نهائياً.');
    }

    /**
     * Decide the image column value from the request.
     *
     * Returns the new value (a stored path, a library URL, or null to clear it),
     * or false when the existing image should be left untouched.
     */
    private function resolveImage(Request $request, ?CourseCategory $category = null): string|false|null
    {
        $replacesExisting = $request->hasFile('image')
            || filled($request->input('image_url'))
            || $request->boolean('remove_image');

        if ($category && $replacesExisting && $category->hasUploadedImage()) {
            $this->storageManager->delete(CourseCategory::STORAGE_DISK, $category->image);
        }

        if ($request->hasFile('image')) {
            $path = $this->storageManager->storeUploadedFile(CourseCategory::STORAGE_DISK, $request->file('image'), 'categories');

            return $path !== false ? $path : null;
        }

        if (filled($request->input('image_url'))) {
            return (string) $request->input('image_url');
        }

        if ($request->boolean('remove_image')) {
            return null;
        }

        return false;
    }

    /**
     * @param  array{query: string, status: string, featured: string, parent: string}  $filters
     * @return Builder<CourseCategory>
     */
    private function filteredQuery(array $filters): Builder
    {
        return CourseCategory::query()
            ->when($filters['query'] !== '', function (Builder $builder) use ($filters) {
                $like = "%{$filters['query']}%";
                $builder->where(function (Builder $inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($filters['status'] !== '', fn (Builder $builder) => $builder->where('is_active', $filters['status'] === 'active'))
            ->when($filters['featured'] !== '', fn (Builder $builder) => $builder->where('is_featured', $filters['featured'] === '1'))
            ->when($filters['parent'] === 'root', fn (Builder $builder) => $builder->whereNull('parent_id'))
            ->when(ctype_digit($filters['parent']), fn (Builder $builder) => $builder->where('parent_id', (int) $filters['parent']));
    }

    /**
     * Only root categories can be parents.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function rootOptions(?int $exceptId = null): array
    {
        return CourseCategory::query()
            ->whereNull('parent_id')
            ->when($exceptId, fn (Builder $builder) => $builder->whereKeyNot($exceptId))
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CourseCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->values()
            ->all();
    }

    private function nextOrder(?int $parentId): int
    {
        return (int) CourseCategory::query()->where('parent_id', $parentId)->max('order') + 1;
    }

    /**
     * @return array{success: mixed, error: mixed}
     */
    private function flash(Request $request): array
    {
        return [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
        ];
    }
}
