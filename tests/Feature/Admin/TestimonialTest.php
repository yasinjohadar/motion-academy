<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Frontend\TestimonialSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.testimonials.list',
            'admin.testimonials.create',
            'admin.testimonials.edit',
            'admin.testimonials.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        Storage::fake(config('storage.fallback_disk', 'public'));
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
            'name' => 'ريم سالم',
            'role' => 'Motion Designer',
            'company' => 'استوديو حرّك',
            'content' => 'تجربة رائعة غيّرت طريقة عملي في التحريك بالكامل.',
            'rating' => 4.5,
            'icon' => 'fas fa-user',
            'color' => '#1a83c1',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 2,
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
            'eyebrow' => 'قالوا عنا',
            'title' => 'تجارب طلابنا',
            'subtitle' => '',
            'limit' => 2,
            'order' => 'rating',
            'featured_only' => false,
            'layout' => 'slider',
            'columns' => 4,
            'show_rating' => false,
            'autoplay' => true,
            ...$overrides,
        ];
    }

    public function test_admin_sees_testimonials_with_counts_and_settings(): void
    {
        Testimonial::factory()->count(2)->create();
        Testimonial::factory()->featured()->create();
        Testimonial::factory()->inactive()->create();

        $this->actingAs($this->adminUser())
            ->get(route('admin.testimonials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testimonials/Index')
                ->has('testimonials.data', 4)
                ->where('counts', ['all' => 4, 'active' => 3, 'featured' => 1])
                ->where('settings.limit', 3)
                ->where('settings.layout', 'grid'));
    }

    public function test_admin_can_filter_testimonials(): void
    {
        Testimonial::factory()->create(['name' => 'ليان']);
        Testimonial::factory()->inactive()->create(['name' => 'عمر']);

        $this->actingAs($this->adminUser())
            ->get(route('admin.testimonials.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.name', 'عمر'));

        $this->actingAs($this->adminUser())
            ->get(route('admin.testimonials.index', ['query' => 'ليان']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.name', 'ليان'));
    }

    public function test_admin_can_create_a_testimonial_with_an_avatar_and_course(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.testimonials.store'), $this->validPayload([
                'course_id' => $course->id,
                'avatar' => UploadedFile::fake()->image('reem.jpg'),
            ]))
            ->assertRedirect(route('admin.testimonials.index'));

        $testimonial = Testimonial::query()->sole();
        $this->assertSame('ريم سالم', $testimonial->name);
        $this->assertSame('4.5', $testimonial->rating);
        $this->assertSame($course->id, $testimonial->course_id);
        $this->assertTrue($testimonial->is_featured);
        $this->assertSame($admin->id, $testimonial->created_by);
        Storage::disk(config('storage.fallback_disk', 'public'))->assertExists($testimonial->avatar);
    }

    public function test_removing_the_avatar_deletes_the_uploaded_file(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin)->post(route('admin.testimonials.store'), $this->validPayload([
            'avatar' => UploadedFile::fake()->image('reem.jpg'),
        ]));
        $testimonial = Testimonial::query()->sole();
        $path = $testimonial->avatar;

        $this->actingAs($admin)
            ->put(route('admin.testimonials.update', $testimonial), $this->validPayload(['remove_avatar' => true]))
            ->assertSessionHasNoErrors();

        $this->assertNull($testimonial->fresh()->avatar);
        Storage::disk(config('storage.fallback_disk', 'public'))->assertMissing($path);
    }

    public function test_invalid_testimonial_input_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.testimonials.store'), $this->validPayload([
                'name' => '',
                'content' => 'قصير',
                'rating' => 4.3,
                'color' => 'red',
                'icon' => 'fas" onclick="x',
                'course_id' => 999,
            ]))
            ->assertSessionHasErrors(['name', 'content', 'rating', 'color', 'icon', 'course_id']);

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_admin_can_toggle_and_delete_a_testimonial(): void
    {
        $admin = $this->adminUser();
        $testimonial = Testimonial::factory()->create();

        $this->actingAs($admin)->post(route('admin.testimonials.toggle-active', $testimonial));
        $this->actingAs($admin)->post(route('admin.testimonials.toggle-featured', $testimonial));

        $testimonial->refresh();
        $this->assertFalse($testimonial->is_active);
        $this->assertTrue($testimonial->is_featured);

        $this->actingAs($admin)->delete(route('admin.testimonials.destroy', $testimonial));
        $this->assertModelMissing($testimonial);
    }

    public function test_admin_can_save_section_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.testimonials.settings'), $this->settingsPayload())
            ->assertSessionHasNoErrors();

        $settings = app(TestimonialSettings::class)->all();
        $this->assertSame('تجارب طلابنا', $settings['title']);
        $this->assertSame('', $settings['subtitle']);
        $this->assertSame(2, $settings['limit']);
        $this->assertSame('slider', $settings['layout']);
        $this->assertSame(4, $settings['columns']);
        $this->assertFalse($settings['show_rating']);
    }

    public function test_invalid_section_settings_are_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.testimonials.settings'), $this->settingsPayload([
                'title' => '',
                'limit' => 100,
                'order' => 'alphabetical',
                'layout' => 'masonry',
                'columns' => 5,
            ]))
            ->assertSessionHasErrors(['title', 'limit', 'order', 'layout', 'columns']);
    }

    public function test_user_without_permission_cannot_manage_testimonials(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $testimonial = Testimonial::factory()->create();

        $this->actingAs($user)->get(route('admin.testimonials.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.testimonials.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put(route('admin.testimonials.settings'), $this->settingsPayload())->assertForbidden();
        $this->actingAs($user)->delete(route('admin.testimonials.destroy', $testimonial))->assertForbidden();

        $this->assertModelExists($testimonial);
    }

    public function test_home_page_shows_the_configured_number_of_active_testimonials_in_order(): void
    {
        Testimonial::factory()->create(['name' => 'الثاني', 'sort_order' => 2]);
        Testimonial::factory()->create(['name' => 'الأول', 'sort_order' => 1]);
        Testimonial::factory()->create(['name' => 'الثالث', 'sort_order' => 3]);
        Testimonial::factory()->inactive()->create(['name' => 'مخفي', 'sort_order' => 0]);
        app(TestimonialSettings::class)->save(['limit' => 2, 'title' => 'تجارب طلابنا']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('تجارب طلابنا')
            ->assertSeeInOrder(['الأول', 'الثاني'])
            ->assertDontSee('الثالث')
            ->assertDontSee('مخفي');
    }

    public function test_home_page_can_sort_by_rating_and_show_featured_only(): void
    {
        Testimonial::factory()->featured()->create(['name' => 'أربع نجوم', 'rating' => 4]);
        Testimonial::factory()->featured()->create(['name' => 'خمس نجوم', 'rating' => 5]);
        Testimonial::factory()->create(['name' => 'غير مميز', 'rating' => 5]);
        app(TestimonialSettings::class)->save(['order' => 'rating', 'featured_only' => true, 'limit' => 0]);

        $this->get(route('home'))
            ->assertSeeInOrder(['خمس نجوم', 'أربع نجوم'])
            ->assertDontSee('غير مميز');
    }

    public function test_cards_render_half_stars_and_only_link_published_courses(): void
    {
        $published = Course::factory()->published()->create(['title' => 'دورة منشورة']);
        $draft = Course::factory()->create(['title' => 'دورة مسودة']);
        Testimonial::factory()->create(['rating' => 3.5, 'course_id' => $published->id, 'sort_order' => 1]);
        Testimonial::factory()->create(['course_id' => $draft->id, 'sort_order' => 2]);

        $this->get(route('home'))
            ->assertSee('<i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>', false)
            ->assertSee(route('courses.show', $published->slug))
            ->assertDontSee('دورة مسودة');
    }

    public function test_slider_layout_and_hidden_rating_are_respected(): void
    {
        Testimonial::factory()->count(4)->create();
        app(TestimonialSettings::class)->save(['layout' => 'slider', 'columns' => 2, 'show_rating' => false, 'limit' => 0]);

        $this->get(route('home'))
            ->assertSee('class="swiper testimonials-swiper"', false)
            ->assertSee('data-columns="2"', false)
            ->assertDontSee('class="testimonial-rating', false);
    }

    public function test_section_is_left_out_when_no_testimonial_is_active(): void
    {
        Testimonial::factory()->inactive()->create();
        TeamMember::factory()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="testimonials-section', false)
            ->assertSee('class="newsletter-section home-zone home-zone--alt section-fade-up"', false);
    }
}
