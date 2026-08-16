<?php

namespace Tests\Feature\Api;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public blog API.
 *
 * Read-only and unauthenticated, so the whole security surface is one question:
 * can anything unpublished escape? Most of this file is that question asked several
 * ways, because `published()` is the only thing between a draft and the internet.
 */
class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ visibility

    public function test_only_published_posts_are_listed(): void
    {
        BlogPost::factory()->published()->create(['title' => 'منشور']);
        BlogPost::factory()->create(['title' => 'مسودة']);

        $response = $this->getJson(route('api.v1.blog.posts.index'));

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'منشور');
    }

    public function test_a_post_scheduled_for_the_future_is_not_listed(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'مجدول',
            'published_at' => now()->addWeek(),
        ]);

        $this->getJson(route('api.v1.blog.posts.index'))->assertJsonCount(0, 'data');
    }

    public function test_a_draft_answers_404_rather_than_403(): void
    {
        $draft = BlogPost::factory()->create(['slug' => 'secret-draft']);

        // 403 would confirm the slug exists; a draft's existence is not public.
        $this->getJson(route('api.v1.blog.posts.show', $draft->slug))->assertNotFound();
    }

    public function test_an_unknown_slug_answers_404(): void
    {
        $this->getJson(route('api.v1.blog.posts.show', 'nope'))->assertNotFound();
    }

    // ------------------------------------------------------------------ payload

    public function test_a_listing_omits_the_article_body(): void
    {
        BlogPost::factory()->published()->create(['content' => 'النص الكامل للمقال']);

        $this->getJson(route('api.v1.blog.posts.index'))
            ->assertOk()
            ->assertJsonPath('data.0.content', null);
    }

    public function test_a_single_post_includes_the_article_body(): void
    {
        $post = BlogPost::factory()->published()->create(['content' => 'النص الكامل للمقال']);

        $this->getJson(route('api.v1.blog.posts.show', $post->slug))
            ->assertOk()
            ->assertJsonPath('data.content', 'النص الكامل للمقال');
    }

    public function test_a_post_carries_its_author_category_and_tags(): void
    {
        $post = BlogPost::factory()->published()->create();
        $post->tags()->attach(BlogTag::factory()->create(['name' => 'لارافيل'])->id);

        $this->getJson(route('api.v1.blog.posts.show', $post->slug))
            ->assertOk()
            ->assertJsonPath('data.author.name', $post->author->name)
            ->assertJsonPath('data.category.slug', $post->category->slug)
            ->assertJsonPath('data.tags.0.name', 'لارافيل');
    }

    public function test_the_listing_is_paginated_with_meta(): void
    {
        BlogPost::factory()->published()->count(3)->create();

        $this->getJson(route('api.v1.blog.posts.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_per_page_is_clamped_so_one_request_cannot_pull_everything(): void
    {
        BlogPost::factory()->published()->count(3)->create();

        $this->getJson(route('api.v1.blog.posts.index', ['per_page' => 5000]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);

        $this->getJson(route('api.v1.blog.posts.index', ['per_page' => 0]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1);
    }

    // ------------------------------------------------------------------ filtering

    public function test_posts_can_be_filtered_by_category(): void
    {
        $wanted = BlogCategory::factory()->create(['slug' => 'laravel']);
        BlogPost::factory()->published()->create(['blog_category_id' => $wanted->id, 'title' => 'مطلوب']);
        BlogPost::factory()->published()->create(['title' => 'غير مطلوب']);

        $this->getJson(route('api.v1.blog.posts.index', ['category' => 'laravel']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مطلوب');
    }

    public function test_posts_can_be_filtered_by_tag(): void
    {
        $tag = BlogTag::factory()->create(['slug' => 'php']);
        BlogPost::factory()->published()->create(['title' => 'موسوم'])->tags()->attach($tag->id);
        BlogPost::factory()->published()->create(['title' => 'بلا وسم']);

        $this->getJson(route('api.v1.blog.posts.index', ['tag' => 'php']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'موسوم');
    }

    public function test_search_does_not_reach_into_drafts(): void
    {
        BlogPost::factory()->create(['title' => 'سرّ مكتوم']);
        BlogPost::factory()->published()->create(['title' => 'سرّ معلن']);

        // Search is the easiest way to accidentally bypass a visibility scope.
        $this->getJson(route('api.v1.blog.posts.index', ['search' => 'سرّ']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'سرّ معلن');
    }

    public function test_featured_posts_can_be_requested_alone(): void
    {
        BlogPost::factory()->published()->featured()->create(['title' => 'مميّز']);
        BlogPost::factory()->published()->create(['title' => 'عادي']);

        $this->getJson(route('api.v1.blog.posts.index', ['featured' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مميّز');
    }

    // ------------------------------------------------------------------ taxonomy

    public function test_categories_and_tags_list_only_active_entries(): void
    {
        BlogCategory::factory()->create(['name' => 'ظاهر']);
        BlogCategory::factory()->inactive()->create(['name' => 'مخفي']);
        BlogTag::factory()->create(['name' => 'وسم']);

        $this->getJson(route('api.v1.blog.categories'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ظاهر');

        $this->getJson(route('api.v1.blog.tags'))->assertOk()->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------ rate limiting

    public function test_the_api_is_rate_limited(): void
    {
        BlogPost::factory()->published()->create();

        $response = $this->getJson(route('api.v1.blog.posts.index'));

        // Laravel does not define the `api` limiter for you, and a missing one makes
        // the middleware a silent no-op — the worst failure mode for a public endpoint.
        // The headers are the proof that it is actually engaged.
        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit', (string) config('api.rate_limit'))
            ->assertHeader('X-RateLimit-Remaining');
    }

    public function test_exceeding_the_limit_returns_429(): void
    {
        config()->set('api.rate_limit', 2);
        // The limiter closure reads config at resolve time, so rebind it for this test.
        app(RateLimiter::class)->for('api', fn ($request) => Limit::perMinute(2)->by('test-key'));

        BlogPost::factory()->published()->create();

        $this->getJson(route('api.v1.blog.posts.index'))->assertOk();
        $this->getJson(route('api.v1.blog.posts.index'))->assertOk();
        $this->getJson(route('api.v1.blog.posts.index'))->assertStatus(429);
    }

    // ------------------------------------------------------------------ efficiency

    public function test_a_listing_does_not_issue_a_query_per_post(): void
    {
        BlogPost::factory()->published()->count(5)->create()
            ->each(fn (BlogPost $p) => $p->tags()->attach(BlogTag::factory()->create()->id));

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $this->getJson(route('api.v1.blog.posts.index'))->assertOk();

        // Author, category and tags are read for every row; without eager loading this
        // is well over twenty queries and grows with the page size.
        $this->assertLessThan(10, count($queries), 'N+1: '.count($queries).' queries for 5 posts.');
    }
}
