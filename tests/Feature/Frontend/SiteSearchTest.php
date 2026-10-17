<?php

namespace Tests\Feature\Frontend;

use App\Enums\CourseStatus;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_returns_suggestions_and_placeholder_phrases(): void
    {
        $category = CourseCategory::factory()->create(['name' => 'الموشن جرافيك']);
        Course::factory()->published()->featured()->create(['course_category_id' => $category->id, 'title' => 'أساسيات After Effects']);

        $this->getJson(route('search.suggest'))
            ->assertOk()
            ->assertJsonPath('query', '')
            ->assertJsonPath('courses.0.title', 'أساسيات After Effects')
            ->assertJsonPath('categories.0.name', 'الموشن جرافيك')
            ->assertJsonFragment(['phrases' => ['الموشن جرافيك', 'أساسيات After Effects']]);
    }

    public function test_query_matches_courses_categories_and_posts(): void
    {
        $category = CourseCategory::factory()->create(['name' => 'تصميم الحركة']);
        Course::factory()->published()->create(['course_category_id' => $category->id, 'title' => 'تصميم شعارات متحركة']);
        BlogPost::query()->create([
            'title' => 'مبادئ تصميم الحركة', 'slug' => 'motion-principles', 'content' => '<p>نص</p>',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('search.suggest', ['q' => 'تصميم']))->assertOk();

        $response->assertJsonPath('courses.0.title', 'تصميم شعارات متحركة')
            ->assertJsonPath('categories.0.name', 'تصميم الحركة')
            ->assertJsonPath('posts.0.title', 'مبادئ تصميم الحركة')
            ->assertJsonPath('total', 3);
        $this->assertStringContainsString('/courses?search=', $response->json('all_url'));
    }

    public function test_drafts_and_inactive_categories_are_not_returned(): void
    {
        $inactive = CourseCategory::factory()->inactive()->create(['name' => 'مخفي']);
        Course::factory()->create(['title' => 'مسودة مخفية', 'status' => CourseStatus::Draft]);
        Course::factory()->published()->create(['course_category_id' => $inactive->id, 'title' => 'دورة مخفية']);

        $this->getJson(route('search.suggest', ['q' => 'مخفي']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
