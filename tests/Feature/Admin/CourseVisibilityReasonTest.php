<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use App\Services\Frontend\CourseCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A course marked "published" can still be missing from the site; the admin
 * list must say why, and agree with what the public catalogue actually shows.
 */
class CourseVisibilityReasonTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        Permission::findOrCreate('admin.courses.list', 'web');
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array<int, string|null>
     */
    protected function reasonsById(): array
    {
        $rows = [];

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.index'))
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = collect($page->toArray()['props']['courses']['data'])->pluck('hidden_reason', 'id')->all();
            });

        return $rows;
    }

    public function test_the_list_explains_every_published_course_the_site_hides(): void
    {
        $visible = Course::factory()->published()->create();

        $inactive = Course::factory()->published()->create();
        $inactive->category->update(['is_active' => false, 'name' => 'مجال موقوف']);

        $trashed = Course::factory()->published()->create();
        $trashed->category->update(['name' => 'مجال محذوف']);
        $trashed->category->delete();

        $scheduled = Course::factory()->published()->create(['published_at' => now()->addDays(3)]);
        $draft = Course::factory()->create();

        $reasons = $this->reasonsById();

        $this->assertNull($reasons[$visible->id]);
        $this->assertStringContainsString('مجال موقوف', $reasons[$inactive->id]);
        $this->assertStringContainsString('غير مفعّل', $reasons[$inactive->id]);
        $this->assertStringContainsString('محذوف', $reasons[$trashed->id]);
        $this->assertStringContainsString('مجدولة', $reasons[$scheduled->id]);
        $this->assertNull($reasons[$draft->id]);

        // The reason is given exactly for the published courses the site leaves out.
        $shown = app(CourseCatalog::class)->visible()->pluck('id')->all();
        foreach ([$visible, $inactive, $trashed, $scheduled] as $course) {
            $this->assertSame(
                $reasons[$course->id] === null,
                in_array($course->id, $shown, true),
                "Course {$course->id}: admin reason and public catalogue disagree."
            );
        }
    }

    public function test_reactivating_the_category_brings_the_course_back(): void
    {
        $course = Course::factory()->published()->create();
        $course->category->update(['is_active' => false]);

        $this->assertNotContains($course->id, app(CourseCatalog::class)->visible()->pluck('id')->all());

        CourseCategory::query()->whereKey($course->course_category_id)->update(['is_active' => true]);

        $this->assertContains($course->id, app(CourseCatalog::class)->visible()->pluck('id')->all());
        $this->get(route('courses.show', $course->slug))->assertOk();
    }
}
