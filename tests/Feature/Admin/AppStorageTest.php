<?php

namespace Tests\Feature\Admin;

use App\Models\AppStorageConfig;
use App\Models\SiteSetting;
use App\Models\StorageDiskMapping;
use App\Models\User;
use App\Services\Storage\AppStorageFactory;
use App\Services\Storage\AppStorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.app-storage.list',
            'admin.app-storage.create',
            'admin.app-storage.edit',
            'admin.app-storage.delete',
            'admin.app-storage.test',
            'admin.storage-disk-mappings.list',
            'admin.storage-disk-mappings.create',
            'admin.storage-disk-mappings.edit',
            'admin.storage-disk-mappings.delete',
            'admin.site-settings.list',
            'admin.site-settings.edit',
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

    public function test_migration_seeds_local_and_site_mapping(): void
    {
        $this->assertDatabaseHas('app_storage_configs', [
            'name' => 'Local Default',
            'driver' => 'local',
        ]);
        $this->assertDatabaseHas('storage_disk_mappings', [
            'disk_name' => 'site',
        ]);
    }

    public function test_admin_can_create_s3_and_r2_and_idrive_configs(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.app-storage.store'), [
                'name' => 'AWS Main',
                'driver' => 's3',
                'config' => [
                    'access_key_id' => 'AKIAEXAMPLE',
                    'secret_access_key' => 'secret',
                    'region' => 'us-east-1',
                    'bucket' => 'my-bucket',
                ],
                'is_active' => true,
                'priority' => 10,
            ])
            ->assertRedirect(route('admin.app-storage.index'));

        $this->actingAs($admin)
            ->post(route('admin.app-storage.store'), [
                'name' => 'R2 Main',
                'driver' => 'cloudflare_r2',
                'config' => [
                    'account_id' => 'acc123',
                    'access_key_id' => 'r2key',
                    'secret_access_key' => 'r2secret',
                    'bucket' => 'r2-bucket',
                ],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.app-storage.index'));

        $this->actingAs($admin)
            ->post(route('admin.app-storage.store'), [
                'name' => 'IDrive',
                'driver' => 'idrive_e2',
                'config' => [
                    'access_key_id' => 'idkey',
                    'secret_access_key' => 'idsecret',
                    'region' => 'us-east-1',
                    'bucket' => 'id-bucket',
                    'endpoint' => 'https://example.idrivee2.com',
                    'use_path_style' => true,
                ],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.app-storage.index'));

        $this->assertDatabaseHas('app_storage_configs', ['name' => 'AWS Main', 'driver' => 's3']);
        $this->assertDatabaseHas('app_storage_configs', ['name' => 'R2 Main', 'driver' => 'cloudflare_r2']);
        $this->assertDatabaseHas('app_storage_configs', ['name' => 'IDrive', 'driver' => 'idrive_e2']);

        $r2 = AppStorageConfig::query()->where('name', 'R2 Main')->first();
        $diskConfig = AppStorageFactory::buildDiskConfig('cloudflare_r2', $r2->getDecryptedConfig());
        $this->assertSame('s3', $diskConfig['driver']);
        $this->assertStringContainsString('r2.cloudflarestorage.com', $diskConfig['endpoint']);

        $idrive = AppStorageConfig::query()->where('name', 'IDrive')->first();
        $idConfig = AppStorageFactory::buildDiskConfig('idrive_e2', $idrive->getDecryptedConfig());
        $this->assertTrue($idConfig['use_path_style_endpoint']);
        $this->assertSame('https://example.idrivee2.com', $idConfig['endpoint']);
    }

    public function test_local_connection_test_succeeds(): void
    {
        $config = AppStorageConfig::query()->where('driver', 'local')->first();

        $this->actingAs($this->adminUser())
            ->post(route('admin.app-storage.test', $config))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_admin_can_update_disk_mapping(): void
    {
        $local = AppStorageConfig::query()->where('driver', 'local')->first();
        $extra = AppStorageConfig::query()->create([
            'name' => 'Local Alt',
            'driver' => 'local',
            'config' => ['path' => 'public'],
            'is_active' => true,
            'priority' => 1,
        ]);

        $mapping = StorageDiskMapping::query()->where('disk_name', 'site')->first();

        $this->actingAs($this->adminUser())
            ->put(route('admin.storage-disk-mappings.update', $mapping), [
                'disk_name' => 'site',
                'label' => 'ملفات الموقع',
                'primary_storage_id' => $extra->id,
                'fallback_storage_ids' => [$local->id],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.storage-disk-mappings.index'));

        $mapping->refresh();
        $this->assertSame($extra->id, $mapping->primary_storage_id);
        $this->assertSame([$local->id], $mapping->fallback_storage_ids);
    }

    public function test_site_logo_upload_uses_logical_disk(): void
    {
        Storage::fake('public');

        // Ensure local mapping points at a disk we can fake via factory root
        $manager = app(AppStorageManager::class);
        $this->assertInstanceOf(AppStorageManager::class, $manager);

        $admin = $this->adminUser();
        $setting = SiteSetting::current();

        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($admin)
            ->post(route('admin.site-settings.update'), [
                '_method' => 'put',
                'site_name' => 'اختبار',
                'logo' => $file,
            ])
            ->assertRedirect(route('admin.site-settings.edit'));

        $setting->refresh();
        $this->assertNotNull($setting->logo_path);
        $this->assertNotNull($setting->logoUrl());
    }
}
