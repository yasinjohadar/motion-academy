<?php

namespace Tests\Feature\Storage;

use App\Enums\HeroSlideVisual;
use App\Models\Course;
use App\Models\CourseLessonAttachment;
use App\Models\HeroSlide;
use App\Models\User;
use App\Services\Storage\AppStorageManager;
use App\Services\Storage\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bugs found in the storage audit, pinned so they stay fixed.
 */
class StorageRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.hero-slides.list', 'admin.hero-slides.edit',
            'admin.courses.list', 'admin.courses.delete', 'admin.courses.restore', 'admin.courses.delete-any',
            'admin.blog-posts.list', 'admin.blog-posts.create',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_a_failed_upload_keeps_the_current_image(): void
    {
        Storage::disk('public')->put('hero/old.jpg', 'old');
        $slide = HeroSlide::factory()->create(['visual' => HeroSlideVisual::Image, 'image' => 'hero/old.jpg']);

        $manager = Mockery::mock(AppStorageManager::class, [app(StorageSettings::class)])->makePartial();
        $manager->shouldReceive('storeUploadedFile')->andReturn(false);
        $this->app->instance(AppStorageManager::class, $manager);

        $this->actingAs($this->adminUser())
            ->put(route('admin.hero-slides.update', $slide), [
                'title' => 'شريحة',
                'visual' => HeroSlideVisual::Image->value,
                'image' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame('hero/old.jpg', $slide->fresh()->image);
        Storage::disk('public')->assertExists('hero/old.jpg');
    }

    public function test_permanently_deleting_a_course_removes_its_lesson_attachment_files(): void
    {
        Storage::disk('local')->put('attachments/notes.pdf', 'pdf');
        $attachment = CourseLessonAttachment::factory()->create([
            'type' => CourseLessonAttachment::TYPE_FILE,
            'url' => null,
            'path' => 'attachments/notes.pdf',
        ]);
        $course = Course::query()->findOrFail($attachment->lesson->course_id);
        $course->delete();

        $this->actingAs($this->adminUser())
            ->delete(route('admin.courses.force-delete', $course->id));

        $this->assertNull(Course::withTrashed()->find($course->id));
        Storage::disk('local')->assertMissing('attachments/notes.pdf');
    }

    public function test_a_media_library_url_is_accepted_as_a_blog_featured_image(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.blog-posts.store'), [
                'title' => 'مقال',
                'content' => '<p>نص</p>',
                'status' => 'draft',
                'featured_image_url' => '/storage/library/2026/a.jpg',
            ]);

        $response->assertSessionDoesntHaveErrors('featured_image_url');
    }
}
