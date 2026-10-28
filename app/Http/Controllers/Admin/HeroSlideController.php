<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HeroSlideVisual;
use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Concerns\ResolvesImageField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HeroSlideRequest;
use App\Http\Requests\Admin\HeroSliderSettingsRequest;
use App\Models\HeroSlide;
use App\Services\Frontend\HeroSliderSettings;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page hero: its slides, in any number, and the slider's behaviour.
 */
class HeroSlideController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource, ResolvesImageField;

    public function __construct(
        private readonly AppStorageManager $storageManager,
        private readonly HeroSliderSettings $settings,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'hero-slides',
            false,
            [
                'edit' => ['toggleActive', 'updateSettings'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $slides = HeroSlide::query()
            ->with('creator:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HeroSlide $slide) => $slide->toAdminArray());

        return Inertia::render('Admin/HeroSlides/Index', [
            'slides' => $slides,
            'settings' => $this->settings->all(),
            'effects' => HeroSliderSettings::EFFECTS,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/HeroSlides/Create', [
            'visuals' => HeroSlideVisual::options(),
            'nextSortOrder' => (int) HeroSlide::query()->max('sort_order') + 1,
        ]);
    }

    public function store(HeroSlideRequest $request): RedirectResponse
    {
        $image = $this->resolveImageField($request, 'image', HeroSlide::IMAGE_DISK, 'hero');

        HeroSlide::create([
            ...$this->slideAttributes($request),
            'image' => $image ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.hero-slides.index')
            ->with('success', 'تمت إضافة الشريحة بنجاح.');
    }

    public function edit(HeroSlide $hero_slide): Response
    {
        $this->authorize('update', $hero_slide);

        return Inertia::render('Admin/HeroSlides/Edit', [
            'slide' => $hero_slide->loadMissing('creator:id,name')->toAdminArray(),
            'visuals' => HeroSlideVisual::options(),
        ]);
    }

    public function update(HeroSlideRequest $request, HeroSlide $hero_slide): RedirectResponse
    {
        $this->authorize('update', $hero_slide);

        $image = $this->resolveImageField(
            $request, 'image', HeroSlide::IMAGE_DISK, 'hero',
            $hero_slide->hasUploadedImage() ? $hero_slide->image : null,
        );

        $hero_slide->update([
            ...$this->slideAttributes($request),
            ...($image !== false ? ['image' => $image] : []),
        ]);

        return redirect()
            ->route('admin.hero-slides.index')
            ->with('success', 'تم تحديث الشريحة بنجاح.');
    }

    public function destroy(HeroSlide $hero_slide): RedirectResponse
    {
        $this->authorize('delete', $hero_slide);

        if ($hero_slide->hasUploadedImage()) {
            $this->storageManager->delete(HeroSlide::IMAGE_DISK, $hero_slide->image);
        }

        $hero_slide->delete();

        return redirect()
            ->route('admin.hero-slides.index')
            ->with('success', 'تم حذف الشريحة.');
    }

    public function toggleActive(HeroSlide $hero_slide): RedirectResponse
    {
        $this->authorize('update', $hero_slide);

        $hero_slide->update(['is_active' => ! $hero_slide->is_active]);

        return back()->with(
            'success',
            $hero_slide->is_active ? 'تم تفعيل الشريحة.' : 'تم إيقاف الشريحة.'
        );
    }

    public function updateSettings(HeroSliderSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        return back()->with('success', 'تم حفظ إعدادات السلايدر.');
    }

    /**
     * @return array<string, mixed>
     */
    private function slideAttributes(HeroSlideRequest $request): array
    {
        $data = $request->validated();

        return [
            'badge_text' => $data['badge_text'] ?? null,
            'badge_icon' => $data['badge_icon'] ?? null,
            'title' => $data['title'],
            'title_highlight' => $data['title_highlight'] ?? null,
            'description' => $data['description'] ?? null,
            'primary_button_label' => $data['primary_button_label'] ?? null,
            'primary_button_url' => $data['primary_button_url'] ?? null,
            'primary_button_icon' => $data['primary_button_icon'] ?? null,
            'secondary_button_label' => $data['secondary_button_label'] ?? null,
            'secondary_button_url' => $data['secondary_button_url'] ?? null,
            'secondary_button_icon' => $data['secondary_button_icon'] ?? null,
            'bullet_label' => $data['bullet_label'] ?? null,
            'visual' => $data['visual'],
            'image_alt' => $data['image_alt'] ?? null,
            'show_stats' => (bool) ($data['show_stats'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }
}
