<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\BlogAiController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BlogTagController;
use App\Http\Requests\Admin\Blog\BlogAiGenerateRequest;
use App\Http\Requests\Admin\Blog\BlogAiStoreRequest;
use App\Http\Requests\Admin\Blog\BlogCategoryStoreRequest;
use App\Http\Requests\Admin\Blog\BlogPostStoreRequest;
use App\Http\Requests\Admin\Blog\BlogTagStoreRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Response as InertiaResponse;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

class BlogAdminRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_post_store_request_requires_title_and_content(): void
    {
        $validator = Validator::make([], (new BlogPostStoreRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
        $this->assertArrayHasKey('content', $validator->errors()->toArray());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_blog_post_store_request_accepts_valid_payload(): void
    {
        $validator = Validator::make([
            'title' => 'مقال تجريبي',
            'content' => '<p>محتوى</p>',
            'status' => 'draft',
            'is_featured' => false,
        ], (new BlogPostStoreRequest)->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_blog_ai_generate_request_validates_options(): void
    {
        $invalid = Validator::make([
            'topic' => '',
            'content_length' => 'huge',
            'tone' => 'angry',
            'language' => 'fr',
        ], (new BlogAiGenerateRequest)->rules());

        $this->assertTrue($invalid->fails());

        $valid = Validator::make([
            'topic' => 'كيف نبني مدونة',
            'content_length' => 'medium',
            'tone' => 'professional',
            'language' => 'ar',
            'generate_seo' => true,
        ], (new BlogAiGenerateRequest)->rules());

        $this->assertFalse($valid->fails());
    }

    public function test_blog_ai_store_request_defaults_status_to_draft(): void
    {
        $request = BlogAiStoreRequest::create('/', 'POST', [
            'title' => 'عنوان',
            'content' => '<p>نص</p>',
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $this->assertSame('draft', $request->validated('status'));
    }

    public function test_category_and_tag_store_requests_require_name(): void
    {
        $this->assertTrue(Validator::make([], (new BlogCategoryStoreRequest)->rules())->fails());
        $this->assertTrue(Validator::make([], (new BlogTagStoreRequest)->rules())->fails());

        $this->assertFalse(Validator::make(
            ['name' => 'تقنية'],
            (new BlogCategoryStoreRequest)->rules(),
        )->fails());

        $this->assertFalse(Validator::make(
            ['name' => 'laravel'],
            (new BlogTagStoreRequest)->rules(),
        )->fails());
    }

    public function test_blog_post_controller_store_syncs_counts_and_tags(): void
    {
        $user = User::factory()->create();
        $category = BlogCategory::query()->create([
            'name' => 'أخبار',
            'slug' => 'news',
            'is_active' => true,
        ]);
        $tag = BlogTag::query()->create([
            'name' => 'عام',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $request = BlogPostStoreRequest::create('/', 'POST', [
            'title' => 'أول مقال',
            'content' => '<p>محتوى المقال</p>',
            'status' => 'published',
            'blog_category_id' => $category->id,
            'tag_ids' => [$tag->id],
            'is_featured' => true,
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        try {
            $this->app->make(BlogPostController::class)->store($request);
        } catch (RouteNotFoundException) {
            // Routes are wired in a separate step; persistence still runs before redirect.
        }

        $this->assertDatabaseHas('blog_posts', [
            'title' => 'أول مقال',
            'author_id' => $user->id,
            'blog_category_id' => $category->id,
            'status' => 'published',
            'is_featured' => 1,
        ]);

        $post = BlogPost::query()->firstOrFail();
        $this->assertTrue($post->tags->contains('id', $tag->id));
        $this->assertSame(1, $category->fresh()->posts_count);
        $this->assertSame(1, $tag->fresh()->posts_count);
    }

    public function test_blog_category_controller_toggle_active(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'مفعّل',
            'slug' => 'active-cat',
            'is_active' => true,
        ]);

        $response = $this->app->make(BlogCategoryController::class)->toggleActive($category);

        $this->assertTrue($response->isRedirect());
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_blog_tag_controller_update_counts(): void
    {
        $tag = BlogTag::query()->create([
            'name' => 'وسم',
            'slug' => 'tag-one',
            'posts_count' => 99,
        ]);
        $post = BlogPost::query()->create([
            'title' => 'مقال',
            'slug' => 'post-one',
            'content' => '<p>x</p>',
            'status' => 'draft',
        ]);
        $post->tags()->attach($tag->id);

        $response = $this->app->make(BlogTagController::class)->updateCounts();

        $this->assertTrue($response->isRedirect());
        $this->assertSame(1, $tag->fresh()->posts_count);
    }

    public function test_blog_ai_controller_create_passes_categories_and_flash(): void
    {
        BlogCategory::query()->create([
            'name' => 'AI',
            'slug' => 'ai',
            'is_active' => true,
        ]);

        $request = Request::create('/admin/blog/ai', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('ai_draft', ['title' => 'مسودة']);
        $request->session()->put('success', 'تم');

        $response = $this->app->make(BlogAiController::class)->create($request);

        $this->assertInstanceOf(InertiaResponse::class, $response);

        $page = (function () {
            return [
                'component' => $this->component,
                'props' => $this->props,
            ];
        })->call($response);

        $this->assertSame('Admin/Blog/Ai/Create', $page['component']);
        $this->assertNotEmpty($page['props']['categories']);
        $this->assertSame(['title' => 'مسودة'], $page['props']['flash']['ai_draft']);
        $this->assertSame('تم', $page['props']['flash']['success']);
    }
}
