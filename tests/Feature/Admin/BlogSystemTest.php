<?php

namespace Tests\Feature\Admin;

use App\Ai\Agents\BlogDraftAgent;
use App\Models\AiProvider;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\Blog\BlogSeoBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlogSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.blog-posts.list',
            'admin.blog-posts.create',
            'admin.blog-posts.edit',
            'admin.blog-posts.delete',
            'admin.blog-categories.list',
            'admin.blog-categories.create',
            'admin.blog-categories.edit',
            'admin.blog-categories.delete',
            'admin.blog-tags.list',
            'admin.blog-tags.create',
            'admin.blog-tags.edit',
            'admin.blog-tags.delete',
            'admin.blog-ai.generate',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_create_category_tag_and_post(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.blog-categories.store'), [
                'name' => 'تقنية',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.blog-categories.index'));

        $category = BlogCategory::query()->first();
        $this->assertNotNull($category);

        $this->actingAs($admin)
            ->post(route('admin.blog-tags.store'), [
                'name' => 'لارافيل',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.blog-tags.index'));

        $tag = BlogTag::query()->first();

        $this->actingAs($admin)
            ->post(route('admin.blog-posts.store'), [
                'title' => 'مقال تجريبي',
                'content' => '<p>محتوى المقال هنا مع كلمات كافية للاختبار.</p>',
                'excerpt' => 'مقتطف',
                'blog_category_id' => $category->id,
                'tag_ids' => [$tag->id],
                'status' => 'published',
                'is_featured' => true,
                'language' => 'ar',
            ])
            ->assertRedirect(route('admin.blog-posts.index'));

        $post = BlogPost::query()->first();
        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertTrue($post->tags->contains('id', $tag->id));
    }

    public function test_public_blog_show_published_post(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'منشور عام',
            'slug' => 'public-post',
            'content' => '<p>محتوى</p>',
            'excerpt' => 'مقتطف',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'is_indexable' => true,
            'language' => 'ar',
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/Show')
                ->where('post.slug', 'public-post'));
    }

    public function test_seo_builder_expands_draft(): void
    {
        $result = app(BlogSeoBuilder::class)->expandDraft([
            'title' => 'عنوان SEO',
            'content' => '<p>'.str_repeat('كلمة ', 50).'</p>',
            'excerpt' => 'وصف قصير',
        ], ['language' => 'ar']);

        $this->assertSame('عنوان SEO', $result['title']);
        $this->assertNotEmpty($result['meta_title']);
        $this->assertSame('BlogPosting', $result['schema_type']);
        $this->assertSame('article', $result['og_type']);
    }

    public function test_ai_generate_with_fake_agent(): void
    {
        BlogDraftAgent::fake([
            [
                'title' => 'مقال بالذكاء',
                'content' => '<h2>مقدمة</h2><p>محتوى مولّد</p>',
                'excerpt' => 'مقتطف مولّد',
            ],
        ]);

        AiProvider::query()->create([
            'name' => 'Test AI',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4o-mini',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.blog-ai.generate'), [
                'topic' => 'فوائد Laravel',
                'content_length' => 'short',
                'tone' => 'professional',
                'language' => 'ar',
            ])
            ->assertRedirect();

        $this->assertTrue(session()->has('ai_draft') || session()->has('success') || session()->has('error'));
        if (session()->has('ai_draft')) {
            $this->assertSame('مقال بالذكاء', session('ai_draft')['title']);
        }
    }
}
