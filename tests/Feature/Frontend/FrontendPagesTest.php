<?php

namespace Tests\Feature\Frontend;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<int|string, mixed>, 2: string}>
     */
    public static function staticPages(): array
    {
        return [
            'home' => ['home', [], 'frontend.pages.home'],
            'courses' => ['courses', [], 'frontend.pages.courses'],
            'categories' => ['categories', [], 'frontend.pages.categories'],
            'about' => ['about', [], 'frontend.pages.about'],
            'who we are' => ['who-we-are', [], 'frontend.pages.who-we-are'],
            'cart' => ['cart', [], 'frontend.pages.cart'],
            'checkout' => ['checkout', [], 'frontend.pages.checkout'],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     */
    #[DataProvider('staticPages')]
    public function test_static_page_renders_with_the_shared_layout(string $route, array $parameters, string $view): void
    {
        $this->get(route($route, $parameters))
            ->assertOk()
            ->assertViewIs($view)
            ->assertSee('id="siteNavbar"', false)
            ->assertSee(asset('frontend/assets/css/style.css'), false);
    }

    public function test_who_we_are_tells_the_story_with_real_numbers_fields_and_instructors(): void
    {
        $instructor = User::factory()->admin()->create(['name' => 'ليلى المدرّبة', 'name_ar' => 'ليلى المدرّبة']);
        $category = CourseCategory::factory()->create(['name' => 'الموشن جرافيك']);
        Course::factory()->published()->count(2)->create([
            'course_category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'lessons_count' => 5,
        ]);
        Course::factory()->create(['instructor_id' => User::factory()->admin()->create(['name' => 'مسودة', 'name_ar' => 'مسودة'])->id]);
        User::factory()->count(3)->student()->create();

        $this->get(route('who-we-are'))
            ->assertOk()
            ->assertSee('نحوّل الأفكار إلى')
            ->assertViewHas('stats', fn (array $stats) => $stats['courses'] === 2 && $stats['lessons'] === 10 && $stats['students'] === 3)
            ->assertSee('الموشن جرافيك')
            ->assertSee(route('courses', ['category' => $category->slug]), false)
            ->assertSee('ليلى المدرّبة')
            // Only people teaching a published course appear in the credits.
            ->assertDontSee('مسودة')
            ->assertSee('<title>من نحن |', false);

        $this->get(route('about'))->assertSee('<title>حول الأكاديمية |', false);
    }

    public function test_layout_uses_the_configured_site_name_not_the_source_brand(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('إديوماتيك');
    }

    public function test_navbar_links_guests_to_login_and_users_to_their_panel(): void
    {
        $this->get(route('home'))->assertSee(route('login'), false);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('لوحتي');
    }

    public function test_newsletter_signup_is_stored_and_validated(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'a@example.com'])
            ->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'a@example.com']);

        $this->postJson(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertUnprocessable();
    }

    public function test_blog_lists_only_published_posts(): void
    {
        $published = $this->blogPost(['title' => 'مقال منشور', 'slug' => 'published-post']);
        $this->blogPost(['title' => 'مسودة', 'slug' => 'draft-post', 'status' => 'draft', 'published_at' => null]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertViewIs('frontend.pages.blog')
            ->assertSee($published->title)
            ->assertDontSee('مسودة');
    }

    public function test_blog_search_category_and_tag_filters(): void
    {
        $category = BlogCategory::factory()->create(['slug' => 'design', 'name' => 'تصميم']);
        $tag = BlogTag::query()->create(['name' => 'لارافيل', 'slug' => 'laravel', 'is_active' => true]);

        $inCategory = $this->blogPost(['title' => 'أساسيات التصميم', 'slug' => 'design-basics', 'blog_category_id' => $category->id]);
        $tagged = $this->blogPost(['title' => 'مقدمة لارافيل', 'slug' => 'laravel-intro']);
        $tagged->tags()->attach($tag);

        $this->get(route('blog.category', 'design'))
            ->assertOk()
            ->assertSee($inCategory->title)
            ->assertDontSee($tagged->title);

        $this->get(route('blog.tag', 'laravel'))
            ->assertOk()
            ->assertSee($tagged->title)
            ->assertDontSee($inCategory->title);

        $this->get(route('blog.search', ['q' => 'لارافيل']))
            ->assertOk()
            ->assertSee($tagged->title)
            ->assertDontSee($inCategory->title);

        $this->get(route('blog.category', 'missing'))->assertNotFound();
    }

    public function test_blog_post_page_renders_seo_tags_and_counts_the_view(): void
    {
        $post = $this->blogPost([
            'title' => 'مقال SEO',
            'slug' => 'seo-post',
            'meta_description' => 'وصف الميتا للمقال',
        ]);

        $this->get(route('blog.show', 'seo-post'))
            ->assertOk()
            ->assertViewIs('frontend.pages.blog-detail')
            ->assertSee('<meta name="description" content="وصف الميتا للمقال">', false)
            ->assertSee('application/ld+json', false);

        $this->assertSame(1, $post->fresh()->views_count);
    }

    public function test_unpublished_blog_post_is_not_found(): void
    {
        $this->blogPost(['slug' => 'hidden', 'status' => 'draft', 'published_at' => null]);

        $this->get(route('blog.show', 'hidden'))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function blogPost(array $attributes = []): BlogPost
    {
        return BlogPost::query()->create([
            'title' => 'مقال',
            'content' => '<p>محتوى المقال</p>',
            'excerpt' => 'مقتطف',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'language' => 'ar',
            ...$attributes,
        ]);
    }
}
