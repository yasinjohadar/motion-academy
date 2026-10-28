<?php

namespace Tests\Feature\Frontend;

use App\Models\HeroSlide;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Frontend\HomeSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        foreach (['admin.site-settings.list', 'admin.site-settings.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_every_section_is_shown_by_default(): void
    {
        HeroSlide::factory()->create();
        Testimonial::factory()->create();
        TeamMember::factory()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="swiper hero-swiper"', false)
            ->assertSee('class="categories-section home-zone home-zone--base', false)
            ->assertSee('class="courses-section home-zone home-zone--alt', false)
            ->assertSee('class="team-section', false)
            ->assertSee('class="testimonials-section', false)
            ->assertSee('class="newsletter-section', false)
            ->assertSee('class="blog-section home-zone home-zone--alt home-zone--last', false);
    }

    public function test_hidden_sections_are_left_out_of_the_home_page(): void
    {
        HeroSlide::factory()->create();
        app(HomeSections::class)->save(['hero' => false, 'team' => false, 'blog' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="swiper hero-swiper"', false)
            ->assertDontSee('home-hero-bridge', false)
            ->assertDontSee('class="team-section', false)
            ->assertDontSee('class="blog-section', false)
            ->assertSee('class="categories-section', false)
            ->assertSee('class="newsletter-section', false);
    }

    public function test_backgrounds_keep_alternating_around_hidden_sections(): void
    {
        Testimonial::factory()->create();
        TeamMember::factory()->create();
        app(HomeSections::class)->save(['courses' => false, 'blog' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="categories-section home-zone home-zone--base section-fade-up"', false)
            ->assertSee('class="team-section home-zone home-zone--alt section-fade-up"', false)
            ->assertSee('class="testimonials-section home-zone home-zone--base section-fade-up"', false)
            ->assertSee('class="newsletter-section home-zone home-zone--alt home-zone--last section-fade-up"', false);
    }

    public function test_admin_sees_and_saves_section_visibility_from_site_settings(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.site-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('homeSections', count(HomeSections::SECTIONS))
                ->where('homeSections.0.key', 'hero')
                ->where('homeSections.0.visible', true));

        $this->actingAs($admin)
            ->put(route('admin.site-settings.update'), [
                'site_name' => 'أكاديمية الاختبار',
                'home_sections' => ['testimonials' => '0', 'newsletter' => '1'],
            ])
            ->assertRedirect(route('admin.site-settings.edit'));

        $sections = app(HomeSections::class)->all();
        $this->assertFalse($sections['testimonials']);
        $this->assertTrue($sections['newsletter']);
        $this->assertTrue($sections['hero']);
    }

    public function test_unknown_sections_are_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.site-settings.update'), [
                'site_name' => 'أكاديمية الاختبار',
                'home_sections' => ['footer' => false],
            ])
            ->assertSessionHasErrors('home_sections');
    }
}
