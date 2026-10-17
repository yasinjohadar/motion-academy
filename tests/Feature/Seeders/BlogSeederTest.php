<?php

namespace Tests\Feature\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Database\Seeders\BlogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_fifteen_published_posts_across_five_categories(): void
    {
        $author = User::factory()->create();

        $this->seed(BlogSeeder::class);

        $this->assertSame(15, BlogPost::query()->published()->count());
        $this->assertSame(5, BlogCategory::query()->where('is_active', true)->count());

        BlogCategory::query()->each(function (BlogCategory $category): void {
            $this->assertGreaterThanOrEqual(3, $category->posts_count, $category->slug);
        });

        $post = BlogPost::query()->where('slug', 'where-to-start-programming')->firstOrFail();
        $this->assertSame('programming', $post->category->slug);
        $this->assertSame($author->id, $post->author_id);
        $this->assertGreaterThan(0, $post->reading_time);
        $this->assertSame(['beginners', 'web'], $post->tags()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(BlogTag::query()->where('slug', 'web')->value('posts_count'), 2);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(BlogSeeder::class);
        $this->seed(BlogSeeder::class);

        $this->assertSame(15, BlogPost::query()->count());
        $this->assertSame(5, BlogCategory::query()->count());
        $this->assertSame(8, BlogTag::query()->count());
    }

    public function test_seeded_posts_render_on_the_public_blog(): void
    {
        $this->seed(BlogSeeder::class);

        $this->get(route('blog.index'))->assertOk()->assertSee('من أين تبدأ تعلّم البرمجة في 2026؟');
        $this->get(route('blog.category', 'design'))->assertOk()->assertSee('مبادئ التصميم الأساسية لكل مبتدئ');
        $this->get(route('blog.show', 'spaced-repetition-explained'))->assertOk()->assertSee('منحنى النسيان');
    }
}
