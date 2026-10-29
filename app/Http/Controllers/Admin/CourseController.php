<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Concerns\ManagesCourseFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\CourseStoreRequest;
use App\Http\Requests\Admin\Course\CourseUpdateRequest;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;
    use ManagesCourseFiles;

    private const SORTS = [
        'latest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'title' => ['title', 'asc'],
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
    ];

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'courses',
            false,
            [
                'edit' => ['toggleFeatured', 'togglePublish', 'curriculum'],
                'admin.courses.create' => ['duplicate'],
                'admin.courses.restore' => ['trash', 'restore', 'forceDelete'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $filters = [
            'query' => trim((string) $request->string('query')),
            'category' => (string) $request->string('category'),
            'instructor' => (string) $request->string('instructor'),
            'status' => in_array($request->string('status')->value(), CourseStatus::values(), true)
                ? $request->string('status')->value() : '',
            'level' => in_array($request->string('level')->value(), CourseLevel::values(), true)
                ? $request->string('level')->value() : '',
            'pricing' => in_array($request->string('pricing')->value(), ['free', 'paid', 'sale'], true)
                ? $request->string('pricing')->value() : '',
            'featured' => in_array($request->string('featured')->value(), ['1', '0'], true)
                ? $request->string('featured')->value() : '',
            'sort' => array_key_exists($request->string('sort')->value(), self::SORTS)
                ? $request->string('sort')->value() : 'latest',
        ];

        [$sortColumn, $sortDirection] = self::SORTS[$filters['sort']];

        $courses = $this->filteredQuery($filters)
            ->with(['category:id,name', 'instructor:id,name', 'landing:id,course_id,is_published'])
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Course $course) => [
                ...$course->toListArray(),
                // null: no landing page yet; otherwise whether it is published.
                'landing_published' => $course->landing?->is_published,
            ]);

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'instructors' => $this->instructorOptions(),
            'statuses' => CourseStatus::options(),
            'levels' => CourseLevel::options(),
            'stats' => [
                'total' => Course::query()->count(),
                'published' => Course::query()->where('status', CourseStatus::Published)->count(),
                'draft' => Course::query()->where('status', CourseStatus::Draft)->count(),
                'free' => Course::query()->where('is_free', true)->count(),
                'trashed' => Course::onlyTrashed()->count(),
            ],
            'flash' => $this->flash($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Courses/Create', [
            ...$this->formOptions(),
            'defaultCategoryId' => $request->integer('category') ?: null,
            'flash' => $this->flash($request),
        ]);
    }

    public function store(CourseStoreRequest $request): RedirectResponse
    {
        $data = $this->courseAttributes($request);
        $slug = Course::slugify((string) (filled($data['slug'] ?? null) ? $data['slug'] : $data['title']));

        $course = Course::query()->create([
            ...$data,
            ...$this->resolveMedia($request),
            'slug' => Course::uniqueSlug($slug),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.courses.curriculum', $course)
            ->with('success', 'تم إنشاء الدورة. أضف الآن أقسام المنهج ودروسه.');
    }

    public function edit(Request $request, Course $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('Admin/Courses/Edit', [
            ...$this->formOptions(),
            'course' => $course->load(['category:id,name', 'instructor:id,name'])->toAdminArray(),
            'flash' => $this->flash($request),
        ]);
    }

    public function update(CourseUpdateRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $this->courseAttributes($request);
        $slugInput = filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $course->slug;

        $oldThumbnail = $course->thumbnail;
        $oldIntro = $course->intro_video_source === VideoSource::Upload ? $course->intro_video : null;

        $course->update([
            ...$data,
            ...$this->resolveMedia($request),
            'slug' => Course::uniqueSlug(Course::slugify($slugInput), $course->id),
        ]);

        // Only once the row points elsewhere can the old files be checked for other users.
        if ($oldThumbnail !== $course->thumbnail) {
            $this->deleteCourseFileIfUnused(Course::IMAGE_DISK, $oldThumbnail);
        }
        if ($oldIntro !== null && $oldIntro !== $course->intro_video) {
            $this->deleteCourseFileIfUnused(Course::VIDEO_DISK, $oldIntro);
        }

        return redirect()
            ->route('admin.courses.index')
            ->with('success', 'تم تحديث الدورة بنجاح.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        $course->delete();

        return back()->with('success', 'تم نقل الدورة إلى سلة المحذوفات.');
    }

    public function curriculum(Request $request, Course $course): Response
    {
        $this->authorize('update', $course);

        $course->load(['category:id,name', 'instructor:id,name', 'sections.lessons.attachments']);

        return Inertia::render('Admin/Courses/Curriculum', [
            'course' => $course->toListArray(),
            'sections' => $course->sections->map(fn (CourseSection $section) => $section->toAdminArray())->values()->all(),
            'lessonTypes' => LessonType::options(),
            'videoSources' => VideoSource::options(),
            'maxVideoUploadMb' => (int) floor((int) config('media.max_video_upload_kb', 512000) / 1024),
            'flash' => $this->flash($request),
        ]);
    }

    public function toggleFeatured(Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $course->update(['is_featured' => ! $course->is_featured]);

        return back()->with('success', $course->is_featured ? 'تم تمييز الدورة.' : 'تم إلغاء تمييز الدورة.');
    }

    public function togglePublish(Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        if ($course->status === CourseStatus::Published) {
            $course->update(['status' => CourseStatus::Draft]);

            return back()->with('success', 'تم إلغاء نشر الدورة وإعادتها إلى المسودات.');
        }

        if ($course->lessons_count === 0) {
            return back()->with('error', 'لا يمكن نشر دورة بدون دروس منشورة. أضف درساً واحداً على الأقل من صفحة المنهج.');
        }

        $course->update(['status' => CourseStatus::Published]);

        return back()->with('success', 'تم نشر الدورة.');
    }

    /**
     * Copy a course with its whole curriculum as a new draft. Files are shared, not copied.
     */
    public function duplicate(Request $request, Course $course): RedirectResponse
    {
        $copy = DB::transaction(function () use ($course, $request) {
            $copy = $course->replicate(['slug', 'published_at', 'lessons_count', 'duration_seconds']);
            $copy->title = $course->title.' (نسخة)';
            $copy->slug = Course::uniqueSlug(Course::slugify($course->slug.'-copy'));
            $copy->status = CourseStatus::Draft;
            $copy->is_featured = false;
            $copy->created_by = $request->user()->id;
            $copy->save();

            foreach ($course->sections()->with('lessons.attachments')->get() as $section) {
                $sectionCopy = $section->replicate();
                $sectionCopy->course_id = $copy->id;
                $sectionCopy->save();

                foreach ($section->lessons as $lesson) {
                    $lessonCopy = $lesson->replicate();
                    $lessonCopy->course_id = $copy->id;
                    $lessonCopy->course_section_id = $sectionCopy->id;
                    $lessonCopy->saveQuietly();

                    // Files are shared, not copied: deletion checks every reference first.
                    foreach ($lesson->attachments as $attachment) {
                        $lessonCopy->attachments()->create($attachment->only([
                            'type', 'title', 'url', 'path', 'original_name', 'size', 'order',
                        ]));
                    }
                }
            }

            $copy->refreshStats();

            return $copy;
        });

        return redirect()
            ->route('admin.courses.edit', $copy)
            ->with('success', 'تم نسخ الدورة مع منهجها كمسودة جديدة.');
    }

    public function trash(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $courses = Course::onlyTrashed()
            ->with([
                'category' => fn ($builder) => $builder->withTrashed()->select('id', 'name', 'deleted_at'),
                'instructor:id,name',
            ])
            ->when($query !== '', fn (Builder $builder) => $builder->search($query))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Course $course) => [
                ...$course->toListArray(),
                'category_trashed' => (bool) $course->category?->trashed(),
            ]);

        return Inertia::render('Admin/Courses/Trash', [
            'courses' => $courses,
            'filters' => ['query' => $query],
            'flash' => $this->flash($request),
        ]);
    }

    public function restore(Course $course): RedirectResponse
    {
        if (! $course->trashed()) {
            return back()->with('error', 'هذه الدورة غير موجودة في سلة المحذوفات.');
        }

        $this->authorize('restore', $course);

        $category = CourseCategory::withTrashed()->find($course->course_category_id);
        if ($category?->trashed()) {
            return back()->with('error', "استرجع تصنيف «{$category->name}» أولاً.");
        }

        $course->restore();

        return back()->with('success', 'تم استرجاع الدورة بنجاح.');
    }

    public function forceDelete(Course $course): RedirectResponse
    {
        if (! $course->trashed()) {
            return back()->with('error', 'يجب نقل الدورة إلى سلة المحذوفات قبل حذفها نهائياً.');
        }

        $this->authorize('forceDelete', $course);

        $files = [
            [Course::IMAGE_DISK, $course->thumbnail],
            [Course::VIDEO_DISK, $course->intro_video_source === VideoSource::Upload ? $course->intro_video : null],
        ];
        foreach ($course->lessons()->with('attachments')->get() as $lesson) {
            $files[] = [Course::VIDEO_DISK, $lesson->hasStoredVideo() ? $lesson->video : null];
            $files[] = [Course::VIDEO_DISK, $lesson->hasStoredAttachment() ? $lesson->attachment : null];
            foreach ($lesson->attachments as $attachment) {
                $files[] = [Course::VIDEO_DISK, $attachment->path];
            }
        }

        $course->forceDelete();

        foreach ($files as [$disk, $path]) {
            $this->deleteCourseFileIfUnused($disk, $path);
        }

        return back()->with('success', 'تم حذف الدورة ومنهجها نهائياً.');
    }

    /**
     * Validated columns, minus the media inputs handled by resolveMedia().
     *
     * @return array<string, mixed>
     */
    private function courseAttributes(CourseStoreRequest $request): array
    {
        $data = $request->validated();
        unset(
            $data['thumbnail'], $data['thumbnail_url'], $data['remove_thumbnail'],
            $data['intro_video'], $data['intro_video_file'], $data['intro_video_source'],
        );

        $isFree = $request->boolean('is_free');

        return [
            ...$data,
            'is_free' => $isFree,
            'is_featured' => $request->boolean('is_featured'),
            'has_certificate' => $request->boolean('has_certificate'),
            'is_indexable' => $request->boolean('is_indexable', true),
            'price' => $isFree ? 0 : ($data['price'] ?? 0),
            'sale_price' => $isFree ? null : ($data['sale_price'] ?? null),
            'sale_ends_at' => $isFree ? null : ($data['sale_ends_at'] ?? null),
            'learning_outcomes' => $data['learning_outcomes'] ?? [],
            'requirements' => $data['requirements'] ?? [],
            'target_audience' => $data['target_audience'] ?? [],
        ];
    }

    /**
     * Thumbnail and intro-video columns from the request; keys are omitted when unchanged.
     *
     * @return array<string, mixed>
     */
    private function resolveMedia(Request $request): array
    {
        $media = [];

        if ($request->hasFile('thumbnail')) {
            $media['thumbnail'] = $this->storeCourseFile(Course::IMAGE_DISK, $request->file('thumbnail'), 'thumbnails', 'thumbnail');
        } elseif (filled($request->input('thumbnail_url'))) {
            $media['thumbnail'] = (string) $request->input('thumbnail_url');
        } elseif ($request->boolean('remove_thumbnail')) {
            $media['thumbnail'] = null;
        }

        $source = VideoSource::tryFrom((string) $request->input('intro_video_source'));

        if ($source === VideoSource::Upload) {
            if ($request->hasFile('intro_video_file')) {
                $media['intro_video'] = $this->storeCourseFile(Course::VIDEO_DISK, $request->file('intro_video_file'), 'intro', 'intro_video_file');
            }
            $media['intro_video_source'] = $source;
        } elseif ($source !== null) {
            $media['intro_video_source'] = $source;
            $media['intro_video'] = $request->input('intro_video') ?: null;
        } else {
            $media['intro_video_source'] = null;
            $media['intro_video'] = null;
        }

        return $media;
    }

    /**
     * @param  array{query: string, category: string, instructor: string, status: string, level: string, pricing: string, featured: string, sort: string}  $filters
     * @return Builder<Course>
     */
    private function filteredQuery(array $filters): Builder
    {
        $categoryIds = ctype_digit($filters['category'])
            ? CourseCategory::query()
                ->whereKey((int) $filters['category'])
                ->orWhere('parent_id', (int) $filters['category'])
                ->pluck('id')
                ->all()
            : [];

        return Course::query()
            ->when($filters['query'] !== '', fn (Builder $builder) => $builder->search($filters['query']))
            ->when($categoryIds !== [], fn (Builder $builder) => $builder->whereIn('course_category_id', $categoryIds))
            ->when($filters['instructor'] === 'none', fn (Builder $builder) => $builder->whereNull('instructor_id'))
            ->when(ctype_digit($filters['instructor']), fn (Builder $builder) => $builder->where('instructor_id', (int) $filters['instructor']))
            ->when($filters['status'] !== '', fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when($filters['level'] !== '', fn (Builder $builder) => $builder->where('level', $filters['level']))
            ->when($filters['pricing'] === 'free', fn (Builder $builder) => $builder->where('is_free', true))
            ->when($filters['pricing'] === 'paid', fn (Builder $builder) => $builder->where('is_free', false))
            ->when($filters['pricing'] === 'sale', fn (Builder $builder) => $builder
                ->where('is_free', false)
                ->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'price')
                ->where(fn (Builder $inner) => $inner->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now())))
            ->when($filters['featured'] !== '', fn (Builder $builder) => $builder->where('is_featured', $filters['featured'] === '1'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'instructors' => $this->instructorOptions(),
            'statuses' => CourseStatus::options(),
            'levels' => CourseLevel::options(),
            'videoSources' => VideoSource::options(),
            'maxVideoUploadMb' => (int) floor((int) config('media.max_video_upload_kb', 512000) / 1024),
        ];
    }

    /**
     * Root categories with their children, for <optgroup> selects.
     *
     * @return array<int, array{id: int, name: string, children: array<int, array{id: int, name: string}>}>
     */
    private function categoryOptions(): array
    {
        return CourseCategory::query()
            ->whereNull('parent_id')
            ->with('children:id,name,parent_id,order')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CourseCategory $root) => [
                'id' => $root->id,
                'name' => $root->name,
                'children' => $root->children
                    ->map(fn (CourseCategory $child) => ['id' => $child->id, 'name' => $child->name])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function instructorOptions(): array
    {
        // Admins, plus whoever already teaches a course (so it shows in the form and filter).
        return User::query()
            ->where(fn ($query) => $query
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', CourseStoreRequest::INSTRUCTOR_ROLES))
                ->orWhereIn('id', Course::query()->whereNotNull('instructor_id')->select('instructor_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values()
            ->all();
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
