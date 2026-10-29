<?php

namespace Tests\Feature\Admin;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'admin.courses.list',
        'admin.courses.create',
        'admin.courses.edit',
        'admin.courses.delete',
        'admin.courses.edit-any',
        'admin.courses.delete-any',
        'admin.courses.restore',
        'admin.course-categories.list',
        'admin.course-categories.delete',
        'admin.course-categories.restore',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        Role::findOrCreate('student', 'web');

        // Uploads must hit the faked disks, not the real local storage the migrations map.
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Instructors are admin accounts (accounts are admins or students only).
     */
    protected function teacher(): User
    {
        return $this->adminUser();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'أساسيات الموشن جرافيك',
            'course_category_id' => CourseCategory::factory()->create()->id,
            'level' => 'beginner',
            'language' => 'ar',
            'status' => 'draft',
            'is_free' => false,
            'price' => 100,
            'currency' => 'USD',
            'short_description' => 'دورة عملية',
            ...$overrides,
        ];
    }

    public function test_index_lists_courses_with_stats(): void
    {
        Course::factory()->count(2)->create();
        Course::factory()->published()->free()->create();

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Index')
                ->has('courses.data', 3)
                ->where('stats.total', 3)
                ->where('stats.published', 1)
                ->where('stats.free', 1));
    }

    public function test_index_filters(): void
    {
        $root = CourseCategory::factory()->create();
        $child = CourseCategory::factory()->childOf($root)->create();
        $teacher = $this->teacher();

        Course::factory()->create(['title' => 'دورة الفرع', 'course_category_id' => $child->id, 'instructor_id' => $teacher->id]);
        Course::factory()->published()->free()->featured()->create(['title' => 'دورة مجانية']);
        Course::factory()->onSale()->create(['title' => 'دورة بخصم', 'level' => 'advanced']);

        $admin = $this->adminUser();
        $assertTitles = function (array $query, array $titles) use ($admin) {
            $this->actingAs($admin)
                ->get(route('admin.courses.index', $query))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('courses.data', count($titles))
                    ->where('courses.data', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all()
                        === collect($titles)->sort()->values()->all()));
        };

        $assertTitles(['category' => (string) $root->id], ['دورة الفرع']);
        $assertTitles(['instructor' => (string) $teacher->id], ['دورة الفرع']);
        $assertTitles(['status' => 'published'], ['دورة مجانية']);
        $assertTitles(['pricing' => 'free'], ['دورة مجانية']);
        $assertTitles(['pricing' => 'sale'], ['دورة بخصم']);
        $assertTitles(['level' => 'advanced'], ['دورة بخصم']);
        $assertTitles(['featured' => '1'], ['دورة مجانية']);
        $assertTitles(['query' => 'بخصم'], ['دورة بخصم']);
    }

    public function test_admin_can_create_course_and_is_sent_to_curriculum(): void
    {
        $teacher = $this->teacher();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.courses.store'), $this->validPayload([
            'instructor_id' => $teacher->id,
            'sale_price' => 70,
            'learning_outcomes' => ['تحريك النصوص', '', 'التحريك ثلاثي الأبعاد'],
        ]));

        $course = Course::query()->firstOrFail();
        $response->assertRedirect(route('admin.courses.curriculum', $course));

        $this->assertSame('أساسيات-الموشن-جرافيك', $course->slug);
        $this->assertSame($teacher->id, $course->instructor_id);
        $this->assertSame($admin->id, $course->created_by);
        $this->assertSame(['تحريك النصوص', 'التحريك ثلاثي الأبعاد'], $course->learning_outcomes);
        $this->assertTrue($course->isOnSale());
        $this->assertSame(70.0, $course->effectivePrice());
        $this->assertSame(30, $course->discountPercent());
    }

    public function test_course_can_be_hidden_from_search_engines(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.courses.store'), $this->validPayload());
        $this->assertTrue(Course::query()->firstOrFail()->is_indexable);

        $course = Course::query()->firstOrFail();
        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $this->validPayload(['is_indexable' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($course->fresh()->is_indexable);
    }

    public function test_free_course_ignores_price_fields(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.store'), $this->validPayload([
                'is_free' => true,
                'price' => 999,
                'sale_price' => 5000,
            ]))
            ->assertSessionHasNoErrors();

        $course = Course::query()->firstOrFail();
        $this->assertTrue($course->is_free);
        $this->assertSame(0.0, (float) $course->price);
        $this->assertNull($course->sale_price);
    }

    public function test_pricing_validation(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload(['price' => null]))
            ->assertSessionHasErrors('price');

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload(['sale_price' => 150]))
            ->assertSessionHasErrors('sale_price');

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload(['sale_price' => 50, 'sale_ends_at' => now()->subDay()->toDateTimeString()]))
            ->assertSessionHasErrors('sale_ends_at');
    }

    public function test_instructor_must_be_an_admin(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.store'), $this->validPayload(['instructor_id' => $student->id]))
            ->assertSessionHasErrors('instructor_id');
    }

    public function test_a_course_keeps_an_instructor_who_is_no_longer_an_admin(): void
    {
        // e.g. the retired "teacher" accounts, now students.
        $former = User::factory()->create();
        $former->assignRole('student');
        $course = Course::factory()->create(['instructor_id' => $former->id]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.edit', $course))
            ->assertInertia(fn ($page) => $page->where('instructors', fn ($list) => collect($list)->contains('id', $former->id)));

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.update', $course), $this->validPayload([
                'course_category_id' => $course->course_category_id,
                'instructor_id' => $former->id,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_intro_video_link_must_match_its_source(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'intro_video_source' => 'youtube',
                'intro_video' => 'https://vimeo.com/123456',
            ]))
            ->assertSessionHasErrors('intro_video');

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'intro_video_source' => 'youtube',
                'intro_video' => 'https://youtu.be/dQw4w9WgXcQ',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            Course::query()->firstOrFail()->toAdminArray()['intro_video_embed'],
        );
    }

    public function test_thumbnail_upload_and_intro_video_go_to_their_disks(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.store'), $this->validPayload([
                'thumbnail' => UploadedFile::fake()->image('cover.jpg'),
                'intro_video_source' => 'upload',
                'intro_video_file' => UploadedFile::fake()->create('intro.mp4', 500, 'video/mp4'),
            ]))
            ->assertSessionHasNoErrors();

        $course = Course::query()->firstOrFail();
        Storage::disk('public')->assertExists($course->thumbnail);
        // Uploaded videos are private: they never land on the public fallback disk.
        Storage::disk('local')->assertExists($course->intro_video);
        Storage::disk('public')->assertMissing($course->intro_video);
    }

    public function test_new_course_cannot_be_created_as_published(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.store'), $this->validPayload(['status' => 'published']))
            ->assertSessionHasErrors('status');
    }

    public function test_update_course_and_publish_with_lessons(): void
    {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['course_section_id' => $section->id]);

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.update', $course), $this->validPayload([
                'title' => 'عنوان جديد',
                'course_category_id' => $course->course_category_id,
                'status' => 'published',
            ]))
            ->assertRedirect(route('admin.courses.index'));

        $course->refresh();
        $this->assertSame('عنوان جديد', $course->title);
        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);
    }

    public function test_toggle_publish_requires_lessons(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.courses.toggle-publish', $course))
            ->assertSessionHas('error');
        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['course_section_id' => $section->id]);

        $this->actingAs($admin)->post(route('admin.courses.toggle-publish', $course));
        $this->assertSame(CourseStatus::Published, $course->fresh()->status);

        $this->actingAs($admin)->post(route('admin.courses.toggle-publish', $course));
        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);
    }

    public function test_toggle_featured(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->adminUser())->post(route('admin.courses.toggle-featured', $course));

        $this->assertTrue($course->fresh()->is_featured);
    }

    public function test_duplicate_copies_course_and_curriculum_as_draft(): void
    {
        $course = Course::factory()->published()->featured()->create(['title' => 'الأصل']);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->count(2)->create(['course_section_id' => $section->id]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.duplicate', $course))
            ->assertSessionHasNoErrors();

        $copy = Course::query()->where('title', 'الأصل (نسخة)')->firstOrFail();
        $this->assertSame(CourseStatus::Draft, $copy->status);
        $this->assertFalse($copy->is_featured);
        $this->assertNotSame($course->slug, $copy->slug);
        $this->assertSame(1, $copy->sections()->count());
        $this->assertSame(2, $copy->lessons()->count());
        $this->assertSame(2, $copy->lessons_count);
        $this->assertSame(2, $course->fresh()->lessons()->count());
    }

    public function test_trash_restore_and_force_delete(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['course_section_id' => $section->id]);

        $this->actingAs($admin)->delete(route('admin.courses.destroy', $course))->assertSessionHas('success');
        $this->assertSoftDeleted($course);

        $this->actingAs($admin)
            ->get(route('admin.courses.trash'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Courses/Trash')->has('courses.data', 1));

        $this->actingAs($admin)->post(route('admin.courses.restore', $course))->assertSessionHas('success');
        $this->assertNotSoftDeleted($course);

        $course->delete();
        $this->actingAs($admin)->delete(route('admin.courses.force-delete', $course))->assertSessionHas('success');

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('course_sections', ['course_id' => $course->id]);
        $this->assertDatabaseMissing('course_lessons', ['course_id' => $course->id]);
    }

    public function test_force_delete_keeps_files_shared_with_a_duplicate(): void
    {
        $disk = Storage::disk('public');
        $disk->put('thumbnails/shared.jpg', 'img');
        $original = Course::factory()->create(['thumbnail' => 'thumbnails/shared.jpg']);
        Course::factory()->create(['thumbnail' => 'thumbnails/shared.jpg']);
        $original->delete();

        $this->actingAs($this->adminUser())->delete(route('admin.courses.force-delete', $original));

        $disk->assertExists('thumbnails/shared.jpg');
    }

    public function test_course_cannot_be_restored_while_its_category_is_trashed(): void
    {
        $course = Course::factory()->create();
        $course->delete();
        $course->category->delete();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.restore', $course))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($course);
    }

    public function test_category_with_courses_cannot_be_deleted(): void
    {
        $course = Course::factory()->create();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->delete(route('admin.course-categories.destroy', $course->category))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($course->category);

        $course->delete();
        $course->category->delete();

        $this->actingAs($admin)
            ->delete(route('admin.course-categories.force-delete', $course->category))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('course_categories', ['id' => $course->course_category_id]);
    }

    public function test_edit_page_renders(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Edit')
                ->where('course.id', $course->id)
                ->has('categories')
                ->has('instructors'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $course = Course::factory()->create();

        $this->actingAs($user)->get(route('admin.courses.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.courses.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->get(route('admin.courses.curriculum', $course))->assertForbidden();
        $this->actingAs($user)->get(route('admin.courses.trash'))->assertForbidden();
    }

    public function test_editor_without_edit_any_cannot_edit_another_instructors_course(): void
    {
        // A panel account with a narrow custom permission set, not the full admin role.
        $teacher = User::factory()->staff()->create();
        $teacher->givePermissionTo(['admin.courses.list', 'admin.courses.edit']);

        $own = Course::factory()->create(['instructor_id' => $teacher->id]);
        $others = Course::factory()->create();

        $this->actingAs($teacher)->get(route('admin.courses.edit', $own))->assertOk();

        // Policy denials come back as an inline flash error (see bootstrap/app.php), not a 403 page.
        $this->actingAs($teacher)->get(route('admin.courses.edit', $others))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($teacher)->get(route('admin.courses.curriculum', $others))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($teacher)
            ->put(route('admin.courses.update', $others), $this->validPayload(['course_category_id' => $others->course_category_id]))
            ->assertSessionHas('error');
        $this->assertNotSame('أساسيات الموشن جرافيك', $others->fresh()->title);
    }

    public function test_short_description_accepts_html_and_limits_visible_text(): void
    {
        $admin = $this->adminUser();
        $formatted = '<p><strong>'.str_repeat('نص ', 120).'</strong></p>';

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload(['short_description' => $formatted]))
            ->assertSessionHasNoErrors();

        $course = Course::query()->firstOrFail();
        $this->assertSame($formatted, $course->short_description);
        $this->assertStringNotContainsString('<strong>', $course->toAdminArray()['short_description_excerpt']);

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->validPayload([
                'title' => 'دورة أخرى',
                'short_description' => '<p>'.str_repeat('a', 501).'</p>',
            ]))
            ->assertSessionHasErrors('short_description');
    }
}
