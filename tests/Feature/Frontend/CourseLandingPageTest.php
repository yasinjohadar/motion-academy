<?php

namespace Tests\Feature\Frontend;

use App\Models\Course;
use App\Models\CourseLanding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_landing_page_renders_every_filled_block(): void
    {
        $landing = CourseLanding::factory()->complete()->create();
        $course = $landing->course;

        $this->get(route('courses.landing', $course->slug))
            ->assertOk()
            ->assertSee($course->title)
            ->assertSee($landing->tagline)
            ->assertSee('<span class="cat">', false)
            ->assertSee('<span>المستوى</span>', false)
            ->assertSee('عن الدورة')
            ->assertSee('data-yt="dQw4w9WgXcQ"', false)
            ->assertSee('ماذا ستتعلم')
            ->assertSee('مميزات الدورة')
            ->assertSee('شهادة إتمام')
            ->assertSee('آراء المتدربين')
            ->assertSee('دورة ممتازة.')
            ->assertSee('https://wa.me/966500000000', false)
            ->assertSee(route('courses.show', $course->slug).'#enroll', false)
            ->assertSee($course->formattedPrice());
    }

    public function test_faqs_render_as_an_accordion_with_structured_data(): void
    {
        $landing = CourseLanding::factory()->complete()->create();

        $this->get(route('courses.landing', $landing->course->slug))
            ->assertSee('الأسئلة الشائعة')
            ->assertSee('<details open>', false)
            ->assertSee('<summary>هل أحتاج خبرة سابقة؟</summary>', false)
            ->assertSee('نعم، بعد إتمام كل الدروس.')
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_whatsapp_link_becomes_a_large_button_under_the_hero_and_at_the_bottom(): void
    {
        $landing = CourseLanding::factory()->complete()->create();

        $html = $this->get(route('courses.landing', $landing->course->slug))
            ->assertSee('<a href="https://wa.me/966500000000" class="wa wa-hero"', false)
            ->assertSee('<a href="https://wa.me/966500000000" class="wa "', false)
            ->assertSee('تواصل معنا على واتساب')
            ->assertSee('https://instagram.com/motion', false)
            ->getContent();

        // Under the hero, at the bottom and floating — never among the small buttons.
        $this->assertSame(3, substr_count($html, 'https://wa.me/966500000000'));
        $this->assertStringContainsString('class="wa-float"', $html);
        $this->assertStringContainsString('<span>تواصل معنا للاشتراك بالكورس</span>', $html);
        // The floating button replaces the sticky sign-up bar.
        $this->assertStringNotContainsString('id="bar"', $html);
    }

    public function test_without_a_whatsapp_link_the_sticky_sign_up_bar_is_kept(): void
    {
        $landing = CourseLanding::factory()->create([
            'social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/motion']],
        ]);

        $this->get(route('courses.landing', $landing->course->slug))
            ->assertDontSee('class="wa', false)
            ->assertSee('id="bar"', false)
            ->assertSee('https://instagram.com/motion', false);
    }

    public function test_empty_blocks_are_not_rendered(): void
    {
        $landing = CourseLanding::factory()->create([
            'description' => '<p> </p>',
            'goals' => [],
        ]);

        $this->get(route('courses.landing', $landing->course->slug))
            ->assertOk()
            ->assertDontSee('عن الدورة')
            ->assertDontSee('شاهد من الدورة')
            ->assertDontSee('ماذا ستتعلم')
            ->assertDontSee('مميزات الدورة')
            ->assertDontSee('آراء المتدربين')
            ->assertDontSee('الأسئلة الشائعة')
            ->assertDontSee('FAQPage')
            ->assertDontSee('تواصل معنا');
    }

    public function test_page_is_standalone_without_the_site_layout_or_heavy_assets(): void
    {
        $landing = CourseLanding::factory()->create();

        $this->get(route('courses.landing', $landing->course->slug))
            ->assertOk()
            ->assertDontSee('bootstrap', false)
            ->assertDontSee('font-awesome', false)
            ->assertDontSee('navbar', false)
            ->assertDontSee('swiper', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_custom_button_price_toggle_and_indexing_are_respected(): void
    {
        $landing = CourseLanding::factory()->create([
            'cta_label' => 'راسلنا على واتساب',
            'cta_url' => 'https://wa.me/966511111111',
            'show_price' => false,
            'allow_indexing' => true,
        ]);

        $this->get(route('courses.landing', $landing->course->slug))
            ->assertSee('راسلنا على واتساب')
            ->assertSee('href="https://wa.me/966511111111"', false)
            ->assertDontSee('class="price"', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_draft_landing_page_and_unpublished_course_are_not_found(): void
    {
        $draft = CourseLanding::factory()->draft()->create();
        $hiddenCourse = CourseLanding::factory()->create(['course_id' => Course::factory()->create()->id]);

        $this->get(route('courses.landing', $draft->course->slug))->assertNotFound();
        $this->get(route('courses.landing', $hiddenCourse->course->slug))->assertNotFound();
        $this->get(route('courses.landing', 'no-such-course'))->assertNotFound();
    }

    public function test_course_without_a_landing_page_is_not_found(): void
    {
        $course = Course::factory()->published()->create();

        $this->get(route('courses.landing', $course->slug))->assertNotFound();
    }

    public function test_editors_can_preview_a_draft_but_others_cannot(): void
    {
        $draft = CourseLanding::factory()->draft()->create();
        $url = route('courses.landing', ['slug' => $draft->course->slug, 'preview' => 1]);

        Permission::findOrCreate('admin.courses.edit', 'web');
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get($url)
            ->assertOk()
            ->assertSee('الصفحة غير منشورة')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        auth()->logout();
        $this->get($url)->assertNotFound();
    }
}
