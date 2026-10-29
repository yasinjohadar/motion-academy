<?php

namespace Tests\Feature\Storage;

use App\Models\Course;
use App\Services\Storage\AppStorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * On this server, public files go to the public disk (/storage) and private
 * files to the private disk, served only through signed links.
 */
class StorageZonesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function storage(): AppStorageManager
    {
        return app(AppStorageManager::class);
    }

    public function test_every_logical_disk_belongs_to_a_zone(): void
    {
        foreach (['site', 'public', 'blog', 'media', 'courses'] as $disk) {
            $this->assertSame('public', AppStorageManager::zone($disk));
        }

        $this->assertSame('private', AppStorageManager::zone('course-videos'));
        $this->assertFalse(AppStorageManager::isKnownDisk('backups'));
    }

    public function test_public_files_go_to_the_public_disk_and_private_files_never_do(): void
    {
        $image = $this->storage()->storeUploadedFile('media', UploadedFile::fake()->image('a.jpg'), 'library');
        $video = $this->storage()->storeUploadedFile('course-videos', UploadedFile::fake()->create('v.mp4', 10), 'lessons');

        Storage::disk('public')->assertExists($image);
        Storage::disk('local')->assertExists($video);
        Storage::disk('public')->assertMissing($video);
        $this->assertSame('/storage/'.$image, $this->storage()->url('media', $image));
    }

    public function test_private_links_are_signed_routes_that_expire(): void
    {
        $url = $this->storage()->temporaryUrl('course-videos', 'lessons/v.mp4', 5);

        $this->assertStringContainsString('/files/course-videos/lessons/v.mp4', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    public function test_urls_and_external_values_pass_through_untouched(): void
    {
        $this->assertSame('/storage/library/a.jpg', $this->storage()->publicUrl('courses', '/storage/library/a.jpg'));
        $this->assertSame('https://cdn.example.com/a.jpg', $this->storage()->publicUrl('courses', 'https://cdn.example.com/a.jpg'));
        $this->assertSame('/storage/thumbnails/a.jpg', $this->storage()->publicUrl('courses', 'thumbnails/a.jpg'));
        $this->assertNull($this->storage()->publicUrl('courses', null));

        $this->assertFalse(AppStorageManager::isStoredPath('/storage/library/a.jpg'));
        $this->assertTrue(AppStorageManager::isStoredPath('thumbnails/a.jpg'));
    }

    public function test_a_media_library_url_picked_as_a_course_thumbnail_is_kept_as_is(): void
    {
        $course = Course::factory()->create(['thumbnail' => '/storage/library/2026/a.jpg']);

        $this->assertSame('/storage/library/2026/a.jpg', $course->thumbnailUrl());
    }

    public function test_deleting_a_url_value_never_touches_storage(): void
    {
        Storage::disk('public')->put('library/a.jpg', 'x');

        $this->assertFalse($this->storage()->delete('media', '/storage/library/a.jpg'));
        Storage::disk('public')->assertExists('library/a.jpg');
    }

    public function test_private_files_support_range_requests_for_video_seeking(): void
    {
        Storage::disk('local')->put('lessons/clip.mp4', '0123456789');

        $url = $this->storage()->temporaryUrl('course-videos', 'lessons/clip.mp4');

        $this->get($url, ['Range' => 'bytes=2-5'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10');
    }

    public function test_private_route_rejects_traversal_segments_but_not_double_dots_in_names(): void
    {
        Storage::disk('local')->put('lessons/clip..final.mp4', 'ok');

        $this->get($this->storage()->temporaryUrl('course-videos', 'lessons/clip..final.mp4'))->assertOk();

        $traversal = URL::temporarySignedRoute('files.private', now()->addMinute(), [
            'disk' => 'course-videos',
            'path' => 'lessons/../../.env',
        ]);
        $this->get($traversal)->assertNotFound();
    }

    public function test_private_route_rejects_unknown_disks(): void
    {
        $url = URL::temporarySignedRoute('files.private', now()->addMinute(), ['disk' => 'backups', 'path' => 'x.zip']);

        $this->get($url)->assertNotFound();
    }
}
