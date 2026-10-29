<?php

namespace Tests\Feature\Admin;

use App\Enums\HeroSlideVisual;
use App\Models\HeroSlide;
use App\Models\User;
use App\Services\Frontend\HeroSliderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeroSlideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.hero-slides.list',
            'admin.hero-slides.create',
            'admin.hero-slides.edit',
            'admin.hero-slides.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        Storage::fake('public');
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return [
            'badge_text' => 'جديد',
            'badge_icon' => 'fas fa-star',
            'title' => 'تعلّم الموشن جرافيك',
            'title_highlight' => 'الموشن',
            'description' => 'وصف الشريحة',
            'primary_button_label' => 'ابدأ',
            'primary_button_url' => '/courses',
            'primary_button_icon' => 'fas fa-arrow-left',
            'bullet_label' => 'البداية',
            'visual' => HeroSlideVisual::Curve->value,
            'show_stats' => true,
            'is_active' => true,
            'sort_order' => 3,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function settingsPayload(array $overrides = []): array
    {
        return [
            'autoplay' => true,
            'autoplay_delay' => 8000,
            'speed' => 700,
            'effect' => 'slide',
            'loop' => false,
            'pause_on_hover' => true,
            'show_navigation' => false,
            'show_pagination' => true,
            'show_progress' => true,
            'max_slides' => 2,
            ...$overrides,
        ];
    }

    public function test_admin_sees_slides_and_slider_settings(): void
    {
        HeroSlide::factory()->count(3)->create();

        $this->actingAs($this->adminUser())
            ->get(route('admin.hero-slides.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/HeroSlides/Index')
                ->has('slides', 3)
                ->where('settings.autoplay_delay', 6000)
                ->where('settings.max_slides', 0));
    }

    public function test_admin_can_create_a_slide(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.hero-slides.store'), $this->validPayload())
            ->assertRedirect(route('admin.hero-slides.index'));

        $slide = HeroSlide::query()->sole();
        $this->assertSame('تعلّم الموشن جرافيك', $slide->title);
        $this->assertSame(HeroSlideVisual::Curve, $slide->visual);
        $this->assertTrue($slide->show_stats);
        $this->assertSame(3, $slide->sort_order);
        $this->assertSame($admin->id, $slide->created_by);
    }

    public function test_admin_can_upload_a_slide_image_and_it_is_replaced_by_a_library_url(): void
    {
        $admin = $this->adminUser();
        $disk = Storage::disk('public');

        $this->actingAs($admin)->post(route('admin.hero-slides.store'), $this->validPayload([
            'visual' => HeroSlideVisual::Image->value,
            'image' => UploadedFile::fake()->image('hero.jpg'),
        ]))->assertSessionHasNoErrors();

        $slide = HeroSlide::query()->sole();
        $uploaded = $slide->image;
        $this->assertTrue($slide->hasUploadedImage());
        $disk->assertExists($uploaded);

        $this->actingAs($admin)->put(route('admin.hero-slides.update', $slide), $this->validPayload([
            'visual' => HeroSlideVisual::Image->value,
            'image_url' => 'https://cdn.example.com/hero.png',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('https://cdn.example.com/hero.png', $slide->fresh()->image);
        $disk->assertMissing($uploaded);
    }

    public function test_update_without_image_fields_keeps_the_existing_image(): void
    {
        $slide = HeroSlide::factory()->withImage()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.hero-slides.update', $slide), $this->validPayload([
                'visual' => HeroSlideVisual::Image->value,
                'title' => 'عنوان معدّل',
            ]))
            ->assertRedirect(route('admin.hero-slides.index'));

        $slide->refresh();
        $this->assertSame('عنوان معدّل', $slide->title);
        $this->assertSame('https://example.com/hero.jpg', $slide->image);
    }

    public function test_invalid_slide_input_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.hero-slides.store'), $this->validPayload([
                'title' => '',
                'visual' => 'video',
                'primary_button_url' => 'javascript:alert(1)',
                'secondary_button_label' => 'بلا رابط',
                'badge_icon' => 'fas" onmouseover="x',
                'starts_at' => '2026-12-01 10:00',
                'ends_at' => '2026-11-01 10:00',
            ]))
            ->assertSessionHasErrors([
                'title',
                'visual',
                'primary_button_url',
                'secondary_button_url',
                'badge_icon',
                'ends_at',
            ]);

        $this->assertDatabaseCount('hero_slides', 0);
    }

    public function test_admin_can_toggle_and_delete_a_slide(): void
    {
        $admin = $this->adminUser();
        $slide = HeroSlide::factory()->create();

        $this->actingAs($admin)->post(route('admin.hero-slides.toggle-active', $slide));
        $this->assertFalse($slide->fresh()->is_active);

        $this->actingAs($admin)
            ->delete(route('admin.hero-slides.destroy', $slide))
            ->assertRedirect(route('admin.hero-slides.index'));

        $this->assertModelMissing($slide);
    }

    public function test_admin_can_save_slider_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.hero-slides.settings'), $this->settingsPayload())
            ->assertSessionHasNoErrors();

        $settings = app(HeroSliderSettings::class)->all();
        $this->assertSame(8000, $settings['autoplay_delay']);
        $this->assertSame('slide', $settings['effect']);
        $this->assertFalse($settings['loop']);
        $this->assertFalse($settings['show_navigation']);
        $this->assertSame(2, $settings['max_slides']);
    }

    public function test_invalid_slider_settings_are_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.hero-slides.settings'), $this->settingsPayload([
                'autoplay_delay' => 100,
                'effect' => 'cube',
                'max_slides' => -1,
            ]))
            ->assertSessionHasErrors(['autoplay_delay', 'effect', 'max_slides']);
    }

    public function test_user_without_permission_cannot_manage_slides(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $slide = HeroSlide::factory()->create();

        $this->actingAs($user)->get(route('admin.hero-slides.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.hero-slides.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put(route('admin.hero-slides.settings'), $this->settingsPayload())->assertForbidden();
        $this->actingAs($user)->delete(route('admin.hero-slides.destroy', $slide))->assertForbidden();

        $this->assertModelExists($slide);
    }

    public function test_home_page_shows_only_active_slides_inside_their_window_in_order(): void
    {
        HeroSlide::factory()->create(['title' => 'الشريحة الثانية', 'sort_order' => 2]);
        HeroSlide::factory()->create(['title' => 'الشريحة الأولى', 'sort_order' => 1]);
        HeroSlide::factory()->inactive()->create(['title' => 'شريحة موقوفة']);
        HeroSlide::factory()->create(['title' => 'شريحة قادمة', 'starts_at' => now()->addDay()]);
        HeroSlide::factory()->create(['title' => 'شريحة منتهية', 'ends_at' => now()->subDay()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['الشريحة الأولى', 'الشريحة الثانية'])
            ->assertDontSee('شريحة موقوفة')
            ->assertDontSee('شريحة قادمة')
            ->assertDontSee('شريحة منتهية');
    }

    public function test_home_page_respects_the_slide_limit_and_highlights_the_title(): void
    {
        app(HeroSliderSettings::class)->save(['max_slides' => 1]);
        HeroSlide::factory()->create([
            'title' => 'نحوّل أفكارك',
            'title_highlight' => 'أفكارك',
            'sort_order' => 1,
        ]);
        HeroSlide::factory()->create(['title' => 'شريحة زائدة', 'sort_order' => 2]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('نحوّل <span class="ma-gradient-text">أفكارك</span>', false)
            ->assertDontSee('شريحة زائدة');
    }

    public function test_slide_text_is_escaped_on_the_home_page(): void
    {
        HeroSlide::factory()->create([
            'title' => '<script>alert(1)</script>',
            'title_highlight' => 'alert',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_home_page_has_no_hero_when_there_are_no_slides(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="swiper hero-swiper"', false);
    }
}
