<?php

namespace Tests\Feature\Frontend;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Services\Seo\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::current()->update([
            'site_name' => 'Motion Academy',
            'tagline' => 'نحوّل الأفكار إلى حركة',
            'seo_description' => 'أكاديمية لتعلّم التحريك وصناعة المحتوى البصري.',
            'twitter_handle' => '@MotionAcademy',
            'google_site_verification' => 'google-code-123',
            'bing_site_verification' => 'bing-code-456',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(TestResponse $response): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $match);
        $this->assertNotEmpty($match, 'The page has no JSON-LD.');

        $document = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        return $document['@graph'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $graph
     * @return array<string, mixed>|null
     */
    private function node(array $graph, string $type): ?array
    {
        return collect($graph)->firstWhere('@type', $type);
    }

    private function course(array $attributes = []): Course
    {
        return Course::factory()->published()->create([
            'title' => 'أساسيات الموشن جرافيك',
            'slug' => 'motion-basics',
            'short_description' => '<p>تعلّم <strong>التحريك</strong> من الصفر.</p>',
            'duration_seconds' => 5400,
            'lessons_count' => 12,
            ...$attributes,
        ]);
    }

    public function test_home_page_uses_site_defaults_and_describes_the_organization(): void
    {
        SocialLink::query()->create(['label' => 'Instagram', 'url' => 'https://instagram.com/motion', 'icon' => 'ri-instagram-line', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('<title>Motion Academy — نحوّل الأفكار إلى حركة</title>', false)
            ->assertSee('<meta name="description" content="أكاديمية لتعلّم التحريك وصناعة المحتوى البصري.">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta name="google-site-verification" content="google-code-123">', false)
            ->assertSee('<meta name="msvalidate.01" content="bing-code-456">', false)
            ->assertSee('<meta name="twitter:site" content="@MotionAcademy">', false)
            ->assertSee('<meta property="og:locale" content="ar_AR">', false)
            ->assertSee('index, follow, max-image-preview:large', false);

        $graph = $this->jsonLd($response);
        $organization = $this->node($graph, 'EducationalOrganization');
        $this->assertSame('Motion Academy', $organization['name']);
        $this->assertSame(['https://instagram.com/motion'], $organization['sameAs']);
        $this->assertStringContainsString('{search_term_string}', $this->node($graph, 'WebSite')['potentialAction']['target']['urlTemplate']);
    }

    public function test_course_page_has_full_meta_and_course_structured_data(): void
    {
        $course = $this->course(['meta_keywords' => 'تحريك, موشن']);

        $response = $this->get(route('courses.show', $course->slug))->assertOk();

        $response->assertSee('<title>أساسيات الموشن جرافيك | Motion Academy</title>', false)
            ->assertSee('<meta name="description" content="تعلّم التحريك من الصفر.">', false)
            ->assertSee('<meta name="keywords" content="تحريك, موشن">', false)
            ->assertSee('<link rel="canonical" href="'.route('courses.show', $course->slug).'">', false)
            ->assertSee('<meta property="og:url" content="'.route('courses.show', $course->slug).'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

        $graph = $this->jsonLd($response);
        $schema = $this->node($graph, 'Course');
        $this->assertSame('أساسيات الموشن جرافيك', $schema['name']);
        $this->assertSame('PT1H30M', $schema['timeRequired']);
        $this->assertSame('Online', $schema['hasCourseInstance'][0]['courseMode']);
        $this->assertSame('49.00', $schema['offers'][0]['price']);
        $this->assertSame('Paid', $schema['offers'][0]['category']);
        $this->assertSame(12, $schema['numberOfLessons']);

        $crumbs = collect($this->node($graph, 'BreadcrumbList')['itemListElement'])->pluck('name')->all();
        $this->assertSame(['الرئيسية', 'الدورات', $course->category->name, $course->title], $crumbs);
    }

    public function test_a_course_hidden_from_search_is_noindex_and_left_out_of_the_sitemap(): void
    {
        $hidden = $this->course(['is_indexable' => false]);
        $shown = Course::factory()->published()->create(['slug' => 'visible-course']);

        $this->get(route('courses.show', $hidden->slug))->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('courses.show', $shown->slug), false)
            ->assertDontSee(route('courses.show', $hidden->slug), false);
    }

    public function test_catalogue_category_page_is_a_landing_page_and_filters_are_not_indexed(): void
    {
        $category = CourseCategory::factory()->create(['name' => 'التحريك', 'slug' => 'animation', 'meta_description' => 'دورات التحريك ثنائي الأبعاد.']);
        Course::factory()->published()->count(2)->create(['course_category_id' => $category->id]);

        $this->get(route('courses', ['category' => 'animation']))
            ->assertSee('<title>دورات التحريك | Motion Academy</title>', false)
            ->assertSee('<meta name="description" content="دورات التحريك ثنائي الأبعاد.">', false)
            ->assertSee('<link rel="canonical" href="'.route('courses', ['category' => 'animation']).'">', false)
            ->assertSee('index, follow', false);

        foreach ([['search' => 'x'], ['levels' => ['beginner']], ['sort' => 'newest'], ['pricing' => 'free']] as $filters) {
            $this->get(route('courses', $filters))
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertSee('<link rel="canonical" href="'.route('courses').'">', false);
        }
    }

    public function test_catalogue_pages_are_chained_with_prev_and_next(): void
    {
        Course::factory()->published()->count(14)->create();

        $this->get(route('courses', ['page' => 2]))
            ->assertSee('<link rel="canonical" href="'.route('courses', ['page' => 2]).'">', false)
            ->assertSee('<link rel="prev" href="'.route('courses').'">', false)
            ->assertSee('الدورات — صفحة 2', false);

        $this->get(route('courses'))
            ->assertSee('<link rel="next" href="'.route('courses', ['page' => 2]).'">', false)
            ->assertDontSee('rel="prev"', false);
    }

    public function test_blog_post_has_article_tags_and_respects_its_own_seo_fields(): void
    {
        $tag = BlogTag::factory()->create(['name' => 'تحريك']);
        $post = BlogPost::factory()->published()->create([
            'title' => 'كيف تبدأ في التحريك',
            'slug' => 'start-animating',
            'meta_title' => 'دليل المبتدئ للتحريك',
            'meta_description' => 'خطوات عملية لبدء التحريك.',
            'og_title' => 'عنوان المشاركة',
            'twitter_creator' => 'author_handle',
        ]);
        $post->tags()->attach($tag);

        $response = $this->get(route('blog.show', $post->slug))->assertOk();

        $response->assertSee('<title>دليل المبتدئ للتحريك | Motion Academy</title>', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:title" content="عنوان المشاركة">', false)
            ->assertSee('<meta property="article:tag" content="تحريك">', false)
            ->assertSee('<meta property="article:section" content="'.$post->category->name.'">', false)
            ->assertSee('<meta name="twitter:creator" content="@author_handle">', false);

        $article = $this->node($this->jsonLd($response), 'BlogPosting');
        $this->assertSame('كيف تبدأ في التحريك', $article['headline']);
        $this->assertSame('تحريك', $article['keywords']);
        $this->assertSame(['@id' => route('home').'#organization'], $article['publisher']);
    }

    public function test_blog_post_noindex_settings_are_honoured(): void
    {
        $post = BlogPost::factory()->published()->create(['is_indexable' => false, 'is_followable' => false]);
        $byMeta = BlogPost::factory()->published()->create(['robots_meta' => 'noindex,follow']);

        $this->get(route('blog.show', $post->slug))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get(route('blog.show', $byMeta->slug))->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('sitemap'))
            ->assertDontSee(route('blog.show', $post->slug), false)
            ->assertDontSee(route('blog.show', $byMeta->slug), false);
    }

    public function test_blog_category_and_tag_pages_render_their_meta_and_search_is_noindex(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'دروس', 'slug' => 'lessons', 'meta_title' => 'دروس التحريك', 'meta_description' => 'دروس خطوة بخطوة.']);
        BlogPost::factory()->published()->create(['blog_category_id' => $category->id]);

        $this->get(route('blog.category', 'lessons'))
            ->assertSee('<title>دروس التحريك | Motion Academy</title>', false)
            ->assertSee('<meta name="description" content="دروس خطوة بخطوة.">', false);

        // The query-string version points at the clean category URL.
        $this->get(route('blog.index', ['category' => 'lessons']))
            ->assertSee('<link rel="canonical" href="'.route('blog.category', 'lessons').'">', false);

        $tag = BlogTag::factory()->create(['slug' => 'tips', 'is_indexable' => false]);
        $this->get(route('blog.tag', $tag->slug))->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('blog.index', ['q' => 'تحريك']))
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_private_pages_are_noindex(): void
    {
        $this->get(route('cart'))->assertSee('<meta name="robots" content="noindex', false);
        $this->get(route('checkout'))->assertSee('<meta name="robots" content="noindex', false);
        $this->get(route('login'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_sitemap_lists_public_content_as_valid_xml(): void
    {
        $category = CourseCategory::factory()->create(['slug' => 'design']);
        $course = Course::factory()->published()->create(['course_category_id' => $category->id, 'slug' => 'figma-course']);
        Course::factory()->create(['slug' => 'draft-course']);
        $post = BlogPost::factory()->published()->create(['slug' => 'hello-world']);
        BlogPost::factory()->create(['slug' => 'draft-post']);

        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }
        $this->assertContains(route('home'), $locations);
        $this->assertContains(route('courses', ['category' => 'design']), $locations);
        $this->assertContains(route('courses.show', $course->slug), $locations);
        $this->assertContains(route('blog.show', $post->slug), $locations);
        $this->assertContains(route('blog.category', $post->category->slug), $locations);
        $this->assertNotContains(route('courses.show', 'draft-course'), $locations);
        $this->assertNotContains(route('blog.show', 'draft-post'), $locations);
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /checkout')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_turning_indexing_off_hides_the_whole_site(): void
    {
        SiteSetting::current()->update(['allow_indexing' => false]);
        $course = $this->course();

        $this->get(route('robots'))->assertSee("User-agent: *\nDisallow: /\n", false)->assertDontSee('Sitemap:');
        $this->get(route('courses.show', $course->slug))->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_iso_durations(): void
    {
        $this->assertSame('PT1H30M', StructuredData::isoDuration(5400));
        $this->assertSame('PT2H', StructuredData::isoDuration(7200));
        $this->assertSame('PT1M', StructuredData::isoDuration(20));
        $this->assertNull(StructuredData::isoDuration(0));
    }
}
