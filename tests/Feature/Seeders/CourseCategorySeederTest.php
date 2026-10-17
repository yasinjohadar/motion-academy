<?php

namespace Tests\Feature\Seeders;

use App\Models\CourseCategory;
use Database\Seeders\CourseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_ten_categories_in_two_levels(): void
    {
        $this->seed(CourseCategorySeeder::class);

        $this->assertSame(10, CourseCategory::query()->count());
        $this->assertSame(4, CourseCategory::query()->whereNull('parent_id')->count());

        $child = CourseCategory::query()->where('slug', 'تطوير-الويب')->firstOrFail();
        $this->assertSame('البرمجة-والتطوير', $child->parent->slug);
        $this->assertTrue($child->parent->isRoot());
    }

    public function test_it_is_idempotent_and_restores_trashed_rows(): void
    {
        $this->seed(CourseCategorySeeder::class);
        CourseCategory::query()->where('slug', 'اللغة-الإنجليزية')->firstOrFail()->delete();

        $this->seed(CourseCategorySeeder::class);

        $this->assertSame(10, CourseCategory::query()->count());
        $this->assertSame(0, CourseCategory::onlyTrashed()->count());
    }
}
