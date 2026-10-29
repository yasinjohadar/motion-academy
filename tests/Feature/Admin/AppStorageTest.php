<?php

namespace Tests\Feature\Admin;

use App\Models\AppStorageConfig;
use App\Models\Backup;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Storage\AppStorageFactory;
use App\Services\Storage\AppStorageManager;
use App\Services\Storage\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
            'admin.app-storage.edit-any',
            'admin.app-storage.delete-any',
            'admin.app-storage.test',
            'admin.app-storage.switch',
            'admin.site-settings.list',
            'admin.site-settings.edit',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * An account whose endpoint refuses connections at once — any real request fails fast.
     *
     * @param  array<string, mixed>  $config
     */
    protected function cloudConfig(array $config = [], array $attributes = []): AppStorageConfig
    {
        return AppStorageConfig::query()->create([
            'name' => 'Bucket',
            'driver' => 's3',
            'config' => [
                'access_key_id' => 'AKIAEXAMPLE1234',
                'secret_access_key' => 'secret',
                'region' => 'us-east-1',
                'bucket' => 'bucket',
                'endpoint' => 'http://127.0.0.1:1',
                ...$config,
            ],
            'is_active' => true,
            ...$attributes,
        ]);
    }

    public function test_storage_screen_shows_the_server_as_the_default(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.app-storage.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AppStorage/Index')
                ->where('current.is_local', true)
                ->has('configs', 0)
                ->where('migration', null));
    }

    public function test_fresh_install_has_no_local_profile_and_no_mappings(): void
    {
        $this->assertDatabaseMissing('app_storage_configs', ['driver' => 'local']);
        $this->assertFalse(Schema::hasTable('storage_disk_mappings'));
        $this->assertTrue(app(AppStorageManager::class)->isLocal());
    }

    public function test_admin_can_create_cloud_accounts_with_per_driver_validation(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.app-storage.store'), [
            'name' => 'AWS Main',
            'driver' => 's3',
            'config' => ['access_key_id' => 'AKIAEXAMPLE', 'secret_access_key' => 'secret', 'region' => 'us-east-1', 'bucket' => 'my-bucket'],
        ])->assertRedirect(route('admin.app-storage.index'));

        $this->actingAs($admin)->post(route('admin.app-storage.store'), [
            'name' => 'R2 Main',
            'driver' => 'cloudflare_r2',
            'config' => ['account_id' => 'acc', 'access_key_id' => 'k', 'secret_access_key' => 's', 'bucket' => 'b', 'public_url' => 'https://pub-1.r2.dev'],
        ])->assertRedirect(route('admin.app-storage.index'));

        $this->assertDatabaseHas('app_storage_configs', ['name' => 'AWS Main', 'driver' => 's3']);
        $this->assertDatabaseHas('app_storage_configs', ['name' => 'R2 Main', 'driver' => 'cloudflare_r2']);

        // R2 needs its public domain; IDrive needs an endpoint; URLs must be web URLs.
        $this->actingAs($admin)->post(route('admin.app-storage.store'), [
            'name' => 'R2 broken',
            'driver' => 'cloudflare_r2',
            'config' => ['account_id' => 'acc', 'access_key_id' => 'k', 'secret_access_key' => 's', 'bucket' => 'b'],
        ])->assertSessionHasErrors('config.public_url');

        $this->actingAs($admin)->post(route('admin.app-storage.store'), [
            'name' => 'IDrive broken',
            'driver' => 'idrive_e2',
            'config' => ['access_key_id' => 'k', 'secret_access_key' => 's', 'region' => 'r', 'bucket' => 'b', 'endpoint' => 'file:///etc/passwd'],
            'cdn_url' => 'not a url',
        ])->assertSessionHasErrors(['config.endpoint', 'cdn_url']);

        $this->actingAs($admin)->post(route('admin.app-storage.store'), [
            'name' => 'Local',
            'driver' => 'local',
            'config' => ['path' => '../../'],
        ])->assertSessionHasErrors('driver');
    }

    public function test_cloud_disks_split_public_and_private_zones_under_prefixes(): void
    {
        $config = ['access_key_id' => 'k', 'secret_access_key' => 's', 'bucket' => 'b', 'url' => 'https://bucket.example.com'];

        $public = AppStorageFactory::buildDiskConfig('s3', $config, 'public', 'https://cdn.example.com/');
        $this->assertSame('public', $public['root']);
        $this->assertSame('https://cdn.example.com', $public['url']);
        $this->assertArrayNotHasKey('visibility', $public);

        $private = AppStorageFactory::buildDiskConfig('s3', $config, 'private');
        $this->assertSame('private', $private['root']);
        $this->assertNull($private['url']);

        $acl = AppStorageFactory::buildDiskConfig('s3', [...$config, 'public_acl' => true], 'public');
        $this->assertSame('public', $acl['visibility']);

        $legacy = AppStorageFactory::buildDiskConfig('s3', [...$config, 'legacy_root' => true], 'public');
        $this->assertArrayNotHasKey('root', $legacy);

        $r2 = AppStorageFactory::buildDiskConfig('cloudflare_r2', ['account_id' => 'acc', 'public_url' => 'https://pub.r2.dev'], 'public');
        $this->assertStringContainsString('acc.r2.cloudflarestorage.com', $r2['endpoint']);
        $this->assertSame('https://pub.r2.dev', $r2['url']);

        // Backups use the bucket root.
        $this->assertArrayNotHasKey('root', AppStorageFactory::buildDiskConfig('s3', $config));
    }

    public function test_public_urls_point_at_the_public_prefix(): void
    {
        $disk = AppStorageFactory::createFromArray('s3', [
            'access_key_id' => 'k', 'secret_access_key' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], 'https://cdn.example.com', 'public');

        $this->assertSame('https://cdn.example.com/public/library/a.jpg', $disk->url('library/a.jpg'));
    }

    public function test_update_keeps_blank_secrets_but_lets_optional_fields_be_cleared(): void
    {
        $config = $this->cloudConfig(['url' => 'https://old.example.com', 'legacy_root' => true]);

        $this->actingAs($this->adminUser())->put(route('admin.app-storage.update', $config), [
            'name' => 'Renamed',
            'driver' => 's3',
            'config' => ['access_key_id' => '', 'secret_access_key' => '', 'region' => 'eu-west-1', 'bucket' => 'bucket', 'url' => ''],
            'is_active' => true,
        ])->assertRedirect(route('admin.app-storage.index'));

        $stored = $config->fresh()->getDecryptedConfig();
        $this->assertSame('AKIAEXAMPLE1234', $stored['access_key_id']);
        $this->assertSame('secret', $stored['secret_access_key']);
        $this->assertSame('eu-west-1', $stored['region']);
        $this->assertArrayNotHasKey('url', $stored);
        $this->assertTrue($stored['legacy_root']);
    }

    public function test_changing_driver_requires_new_keys_and_drops_old_settings(): void
    {
        $config = $this->cloudConfig(['url' => 'https://old.example.com']);

        $this->actingAs($this->adminUser())->put(route('admin.app-storage.update', $config), [
            'name' => 'Now R2',
            'driver' => 'cloudflare_r2',
            'config' => ['account_id' => 'acc', 'bucket' => 'b', 'public_url' => 'https://pub.r2.dev'],
        ])->assertSessionHasErrors(['config.access_key_id', 'config.secret_access_key']);
    }

    public function test_the_storage_in_use_cannot_be_deleted_disabled_or_changed(): void
    {
        $config = $this->cloudConfig();
        app(StorageSettings::class)->setActiveConfigId($config->id);
        $admin = $this->adminUser();

        $this->actingAs($admin)->delete(route('admin.app-storage.destroy', $config))->assertSessionHas('error');
        $this->assertModelExists($config);

        $this->actingAs($admin)->put(route('admin.app-storage.update', $config), [
            'name' => 'Bucket',
            'driver' => 's3',
            'config' => ['region' => 'us-east-1', 'bucket' => 'bucket'],
            'is_active' => false,
        ])->assertSessionHas('error');
        $this->assertTrue($config->fresh()->is_active);
    }

    public function test_an_account_holding_backups_cannot_be_deleted(): void
    {
        $config = $this->cloudConfig();
        Backup::query()->create([
            'name' => 'b', 'type' => 'manual', 'backup_type' => 'database', 'storage_driver' => 's3',
            'storage_config_id' => $config->id, 'status' => 'completed', 'retention_days' => 7,
        ]);

        $this->actingAs($this->adminUser())
            ->delete(route('admin.app-storage.destroy', $config))
            ->assertSessionHas('error');

        $this->assertModelExists($config);
    }

    public function test_testing_with_stored_secrets_requires_permission_to_edit_that_account(): void
    {
        $config = $this->cloudConfig(attributes: ['created_by' => User::factory()->create()->id]);

        $tester = User::factory()->staff()->create();
        $tester->givePermissionTo(['admin.app-storage.list', 'admin.app-storage.test', 'admin.app-storage.edit']);

        $this->actingAs($tester)
            ->post(route('admin.app-storage.test-draft'), [
                'driver' => 's3',
                'config' => ['endpoint' => 'https://attacker.example.com'],
                'config_id' => $config->id,
            ])
            ->assertSessionHas('error', fn ($message) => ! str_contains($message, 'فشل الاختبار'));
    }

    public function test_test_endpoint_rejects_non_web_urls(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.app-storage.test-draft'), [
                'driver' => 's3',
                'config' => ['endpoint' => 'gopher://internal:70'],
            ])
            ->assertSessionHasErrors('config.endpoint');
    }

    public function test_switching_is_refused_when_the_account_fails_its_test(): void
    {
        $config = $this->cloudConfig();

        $this->actingAs($this->adminUser())
            ->post(route('admin.app-storage.switch'), ['target' => $config->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('storage_migrations', 0);
        $this->assertTrue(app(AppStorageManager::class)->isLocal());
    }

    public function test_switching_to_where_files_already_are_is_refused(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.app-storage.switch'), ['target' => 'local'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('storage_migrations', 0);
    }

    public function test_switching_needs_its_own_permission(): void
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo(['admin.app-storage.list', 'admin.app-storage.edit']);

        $this->actingAs($user)
            ->post(route('admin.app-storage.switch'), ['target' => 'local'])
            ->assertForbidden();
    }

    public function test_site_logo_upload_goes_to_the_public_zone(): void
    {
        Storage::fake('public');
        $setting = SiteSetting::current();

        $this->actingAs($this->adminUser())
            ->post(route('admin.site-settings.update'), [
                '_method' => 'put',
                'site_name' => 'اختبار',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect(route('admin.site-settings.edit'));

        $setting->refresh();
        Storage::disk('public')->assertExists($setting->logo_path);
        $this->assertSame('/storage/'.$setting->logo_path, $setting->logoUrl());
    }

    public function test_replacing_the_logo_deletes_the_old_file_only_after_saving(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.site-settings.update'), [
            '_method' => 'put', 'site_name' => 'اختبار', 'logo' => UploadedFile::fake()->image('a.png'),
        ]);
        $old = SiteSetting::current()->logo_path;

        $this->actingAs($admin)->post(route('admin.site-settings.update'), [
            '_method' => 'put', 'site_name' => 'اختبار', 'logo' => UploadedFile::fake()->image('b.png'),
        ]);
        $new = SiteSetting::current()->logo_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }
}
