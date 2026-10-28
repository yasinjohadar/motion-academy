<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Concerns\ResolvesImageField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestimonialRequest;
use App\Http\Requests\Admin\TestimonialSettingsRequest;
use App\Models\Course;
use App\Models\Testimonial;
use App\Services\Frontend\TestimonialSettings;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Learner testimonials and how the home page section presents them.
 */
class TestimonialController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource, ResolvesImageField;

    public function __construct(
        private readonly AppStorageManager $storageManager,
        private readonly TestimonialSettings $settings,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'testimonials',
            false,
            [
                'edit' => ['toggleActive', 'toggleFeatured', 'updateSettings'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));
        $status = $request->string('status')->toString();

        $testimonials = Testimonial::query()
            ->with('course:id,title')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('role', 'like', "%{$query}%")
                        ->orWhere('company', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($status === 'featured', fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Testimonial $testimonial) => $testimonial->toAdminArray());

        return Inertia::render('Admin/Testimonials/Index', [
            'testimonials' => $testimonials,
            'counts' => [
                'all' => Testimonial::query()->count(),
                'active' => Testimonial::query()->active()->count(),
                'featured' => Testimonial::query()->active()->where('is_featured', true)->count(),
            ],
            'settings' => $this->settings->all(),
            'options' => $this->settingsOptions(),
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
        return Inertia::render('Admin/Testimonials/Create', [
            'courses' => $this->courseOptions(),
            'nextSortOrder' => (int) Testimonial::query()->max('sort_order') + 1,
        ]);
    }

    public function store(TestimonialRequest $request): RedirectResponse
    {
        $avatar = $this->resolveImageField($request, 'avatar', Testimonial::IMAGE_DISK, 'testimonials');

        Testimonial::create([
            ...$this->attributes($request),
            'avatar' => $avatar ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'تمت إضافة الرأي بنجاح.');
    }

    public function edit(Testimonial $testimonial): Response
    {
        $this->authorize('update', $testimonial);

        return Inertia::render('Admin/Testimonials/Edit', [
            'testimonial' => $testimonial->loadMissing('course:id,title')->toAdminArray(),
            'courses' => $this->courseOptions(),
        ]);
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('update', $testimonial);

        $avatar = $this->resolveImageField(
            $request, 'avatar', Testimonial::IMAGE_DISK, 'testimonials',
            $testimonial->hasUploadedAvatar() ? $testimonial->avatar : null,
        );

        $testimonial->update([
            ...$this->attributes($request),
            ...($avatar !== false ? ['avatar' => $avatar] : []),
        ]);

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'تم تحديث الرأي بنجاح.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('delete', $testimonial);

        if ($testimonial->hasUploadedAvatar()) {
            $this->storageManager->delete(Testimonial::IMAGE_DISK, $testimonial->avatar);
        }

        $testimonial->delete();

        return back()->with('success', 'تم حذف الرأي.');
    }

    public function toggleActive(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('update', $testimonial);

        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return back()->with('success', $testimonial->is_active ? 'تم إظهار الرأي.' : 'تم إخفاء الرأي.');
    }

    public function toggleFeatured(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('update', $testimonial);

        $testimonial->update(['is_featured' => ! $testimonial->is_featured]);

        return back()->with('success', $testimonial->is_featured ? 'تم تمييز الرأي.' : 'تم إلغاء تمييز الرأي.');
    }

    public function updateSettings(TestimonialSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->settings());

        return back()->with('success', 'تم حفظ إعدادات قسم الآراء.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TestimonialRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'company' => $data['company'] ?? null,
            'content' => $data['content'],
            'rating' => $data['rating'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function courseOptions(): array
    {
        return Course::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Course $course) => ['id' => $course->id, 'title' => $course->title])
            ->all();
    }

    /**
     * @return array<string, array<int, array{value: int|string, label: string}>>
     */
    private function settingsOptions(): array
    {
        $pairs = fn (array $options) => array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($options),
            $options,
        );

        return [
            'orders' => $pairs(TestimonialSettings::ORDERS),
            'layouts' => $pairs(TestimonialSettings::LAYOUTS),
            'columns' => array_map(fn (int $count) => ['value' => $count, 'label' => (string) $count], TestimonialSettings::COLUMNS),
        ];
    }
}
