<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.media.list',
            'admin.media.create',
            'admin.media.edit',
            'admin.media.delete',
            'admin.media.show',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());

        Storage::fake(config('storage.fallback_disk', 'public'));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_view_media_studio(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.media.index'))
            ->assertOk();
    }

    public function test_upload_processes_image_to_webp_with_variants(): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('No image driver available.');
        }

        $file = UploadedFile::fake()->image('hero.jpg', 1200, 800);

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.media.store'), [
                'file' => $file,
                'title' => 'صورة تجريبية',
                'alt' => 'وصف',
            ]);

        $response->assertRedirect();

        $media = Media::query()->latest('id')->first();
        $this->assertNotNull($media);
        $this->assertSame('image/webp', $media->mime);
        $this->assertSame('webp', $media->extension);
        $this->assertSame('صورة تجريبية', $media->title);
        $this->assertSame('وصف', $media->alt);
        $this->assertTrue($media->variants()->where('name', 'thumb')->exists());
        $this->assertTrue($media->variants()->where('name', 'medium')->exists());
        $this->assertTrue($media->variants()->where('name', 'large')->exists());
        $this->assertNotNull($media->original_path);
    }

    public function test_upload_json_endpoint_for_tinymce(): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('No image driver available.');
        }

        $file = UploadedFile::fake()->image('editor.png', 400, 300);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.media.upload'), ['file' => $file])
            ->assertOk()
            ->assertJsonStructure(['location', 'media' => ['id', 'url']]);
    }

    public function test_can_update_media_meta(): void
    {
        $media = Media::query()->create([
            'uuid' => fake()->uuid(),
            'disk' => 'media',
            'path' => 'library/test.webp',
            'filename' => 'test.webp',
            'mime' => 'image/webp',
            'extension' => 'webp',
            'size' => 1234,
            'width' => 100,
            'height' => 80,
            'title' => 'قديم',
        ]);

        $this->actingAs($this->adminUser())
            ->put(route('admin.media.update', $media), [
                'title' => 'جديد',
                'alt' => 'بديل',
                'caption' => 'تعليق',
                'description' => 'وصف أطول',
            ])
            ->assertRedirect();

        $media->refresh();
        $this->assertSame('جديد', $media->title);
        $this->assertSame('بديل', $media->alt);
        $this->assertSame('تعليق', $media->caption);
    }

    public function test_guest_cannot_access_media(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect();
    }
}
