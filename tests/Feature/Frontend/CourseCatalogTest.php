<?php

namespace Tests\Feature\Frontend;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course with one section and one published lesson.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publishedCourse(array $attributes = []): Course
    {
        $course = Course::factory()->published()->create($attributes);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['course_section_id' => $section->id, 'duration_seconds' => 600]);

        return $course->fresh();
    }

    public function test_home_shows_real_courses_categories_posts_and_stats(): void
    {
        $category = CourseCategory::factory()->create(['name' => 'تصميم الحركة']);
        $course = $this->publishedCourse(['title' => 'دورة الموشن', 'course_category_id' => $category->id]);
        Course::factory()->create(['title' => 'مسودة مخفية', 'course_category_id' => $category->id]);
        BlogPost::query()->create([
            'title' => 'مقال الرئيسية', 'slug' => 'home-post', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now()->subHour(), 'language' => 'ar',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('دورة الموشن')
            ->assertSee('تصميم الحركة')
            ->assertSee('مقال الرئيسية')
            ->assertDontSee('مسودة مخفية')
            ->assertViewHas('stats', fn (array $stats) => $stats['courses'] === 1 && $stats['lessons'] === 1)
            ->assertSee(route('courses.show', $course->slug), false);
    }

    public function test_courses_in_inactive_categories_are_hidden(): void
    {
        $hiddenCategory = CourseCategory::factory()->inactive()->create();
        $hidden = $this->publishedCourse(['title' => 'دورة تصنيف معطل', 'course_category_id' => $hiddenCategory->id]);

        $this->get(route('courses'))->assertOk()->assertDontSee('دورة تصنيف معطل');
        $this->get(route('courses.show', $hidden->slug))->assertNotFound();
    }

    public function test_courses_page_filters_by_root_category_including_children(): void
    {
        $root = CourseCategory::factory()->create(['slug' => 'programming']);
        $child = CourseCategory::factory()->childOf($root)->create();
        $this->publishedCourse(['title' => 'دورة الفرع', 'course_category_id' => $child->id]);
        $this->publishedCourse(['title' => 'دورة أخرى']);

        $this->get(route('courses', ['categories' => ['programming']]))
            ->assertOk()
            ->assertSee('دورة الفرع')
            ->assertDontSee('دورة أخرى');
    }

    public function test_search_endpoint_filters_by_level_price_and_text(): void
    {
        $this->publishedCourse(['title' => 'Laravel للمبتدئين', 'level' => 'beginner', 'price' => 50]);
        $this->publishedCourse(['title' => 'Docker متقدم', 'level' => 'advanced', 'price' => 200]);
        $this->publishedCourse(['title' => 'React بخصم', 'level' => 'advanced', 'price' => 300, 'sale_price' => 80, 'sale_ends_at' => now()->addDay()]);

        $beginner = $this->getJson(route('courses.search', ['levels' => ['beginner']]))->assertOk();
        $beginner->assertJsonPath('count', 1);
        $this->assertStringContainsString('Laravel للمبتدئين', $beginner->json('html'));

        // The sale price is what counts against the price ceiling.
        $cheap = $this->getJson(route('courses.search', ['price_max' => 100]))->assertJsonPath('count', 2);
        $this->assertStringContainsString('React بخصم', $cheap->json('html'));
        $this->assertStringNotContainsString('Docker متقدم', $cheap->json('html'));

        $docker = $this->getJson(route('courses.search', ['search' => 'Docker']))->assertJsonPath('count', 1);
        $this->assertStringContainsString('Docker متقدم', $docker->json('html'));
    }

    public function test_search_sorts_by_effective_price(): void
    {
        $this->publishedCourse(['title' => 'الأغلى', 'price' => 500]);
        $this->publishedCourse(['title' => 'الأرخص بالخصم', 'price' => 400, 'sale_price' => 10, 'sale_ends_at' => now()->addDay()]);
        $this->publishedCourse(['title' => 'المتوسط', 'price' => 100]);

        $html = $this->getJson(route('courses.search', ['sort' => 'price-asc']))->json('html');

        $this->assertTrue(
            strpos($html, 'الأرخص بالخصم') < strpos($html, 'المتوسط')
            && strpos($html, 'المتوسط') < strpos($html, 'الأغلى'),
        );
    }

    public function test_navbar_live_search_returns_a_limited_list(): void
    {
        foreach (range(1, 4) as $i) {
            $this->publishedCourse(['title' => "دورة بحث {$i}"]);
        }

        $this->getJson(route('courses.search', ['search' => 'بحث', 'limit' => 2]))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('pagination', '');
    }

    public function test_search_with_no_results_returns_the_empty_state(): void
    {
        $response = $this->getJson(route('courses.search', ['search' => 'لا يوجد']))
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->assertStringContainsString('لم يتم العثور على كورسات', $response->json('html'));
    }

    public function test_course_page_shows_published_curriculum_pricing_and_related(): void
    {
        $instructor = User::factory()->create(['name_ar' => 'المدرّس أحمد']);
        $course = Course::factory()->published()->create([
            'title' => 'دورة التفاصيل',
            'instructor_id' => $instructor->id,
            'price' => 100,
            'sale_price' => 60,
            'sale_ends_at' => now()->addWeek(),
            'learning_outcomes' => ['تحريك النصوص'],
            'has_certificate' => true,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id, 'title' => 'المقدمة']);
        CourseLesson::factory()->preview()->create(['course_section_id' => $section->id, 'title' => 'درس مجاني']);
        CourseLesson::factory()->hidden()->create(['course_section_id' => $section->id, 'title' => 'درس مخفي']);
        CourseSection::factory()->create(['course_id' => $course->id, 'title' => 'قسم فارغ']);
        $related = $this->publishedCourse(['title' => 'دورة مقترحة', 'course_category_id' => $course->course_category_id]);

        $this->get(route('courses.show', $course->slug))
            ->assertOk()
            ->assertViewIs('frontend.pages.course-detail')
            ->assertSee('دورة التفاصيل')
            ->assertSee('المدرّس أحمد')
            ->assertSee('تحريك النصوص')
            ->assertSee('درس مجاني')
            ->assertSee('معاينة مجانية')
            ->assertDontSee('درس مخفي')
            ->assertDontSee('قسم فارغ')
            ->assertSee('$60')
            ->assertSee('خصم 40%')
            ->assertSee('شهادة إتمام')
            ->assertSee($related->title);
    }

    public function test_draft_course_page_is_not_found(): void
    {
        $draft = Course::factory()->create();

        $this->get(route('courses.show', $draft->slug))->assertNotFound();
    }

    public function test_categories_page_counts_courses_of_sub_categories(): void
    {
        $root = CourseCategory::factory()->create(['name' => 'البرمجة']);
        $child = CourseCategory::factory()->childOf($root)->create();
        $this->publishedCourse(['course_category_id' => $root->id]);
        $this->publishedCourse(['course_category_id' => $child->id]);

        $this->get(route('categories'))
            ->assertOk()
            ->assertSee('البرمجة')
            ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $root->id)->courses_count === 2);
    }

    public function test_free_course_shows_free_price(): void
    {
        $course = $this->publishedCourse(['is_free' => true]);

        $this->get(route('courses.show', $course->slug))->assertOk()->assertSee('مجاناً');
    }

    public function test_search_filters_by_pricing_type(): void
    {
        $this->publishedCourse(['title' => 'دورة مجانية', 'is_free' => true, 'price' => 0]);
        $this->publishedCourse(['title' => 'دورة بخصم', 'price' => 100, 'sale_price' => 50, 'sale_ends_at' => now()->addDay()]);
        $this->publishedCourse(['title' => 'خصم منتهي', 'price' => 100, 'sale_price' => 50, 'sale_ends_at' => now()->subDay()]);

        $free = $this->getJson(route('courses.search', ['pricing' => 'free']))->assertJsonPath('count', 1);
        $this->assertStringContainsString('دورة مجانية', $free->json('html'));

        $sale = $this->getJson(route('courses.search', ['pricing' => 'sale']))->assertJsonPath('count', 1);
        $this->assertStringContainsString('دورة بخصم', $sale->json('html'));

        $this->getJson(route('courses.search', ['pricing' => 'paid']))->assertJsonPath('count', 2);
        $this->getJson(route('courses.search', ['pricing' => 'bogus']))->assertJsonPath('count', 3);
    }
}
