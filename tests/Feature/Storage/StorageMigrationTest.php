<?php

namespace Tests\Feature\Storage;

use App\Jobs\MigrateStorageFiles;
use App\Models\AppStorageConfig;
use App\Models\StorageMigration;
use App\Services\Storage\AppStorageManager;
use App\Services\Storage\StorageMigrator;
use App\Services\Storage\StorageSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Copying files to another storage, then switching. The cloud account is
 * replaced by two faked disks, so the copy logic runs without a network.
 */
class StorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    private AppStorageConfig $cloud;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('cloud-public');
        Storage::fake('cloud-private');

        $this->cloud = AppStorageConfig::query()->create([
            'name' => 'Bucket',
            'driver' => 's3',
            'config' => ['access_key_id' => 'k', 'secret_access_key' => 's', 'region' => 'r', 'bucket' => 'b'],
            'is_active' => true,
        ]);

        // A real manager (constructor and all) whose cloud disks are fakes.
        $manager = Mockery::mock(AppStorageManager::class, [app(StorageSettings::class)])->makePartial();
        $manager->shouldReceive('diskForTarget')->andReturnUsing(
            fn (?AppStorageConfig $config, string $zone): Filesystem => $config
                ? Storage::disk("cloud-{$zone}")
                : Storage::disk($zone === 'public' ? 'public' : 'local'),
        );
        $this->app->instance(AppStorageManager::class, $manager);
    }

    protected function migrator(): StorageMigrator
    {
        return app(StorageMigrator::class);
    }

    public function test_all_files_are_copied_then_the_site_switches(): void
    {
        Storage::disk('public')->put('library/a.jpg', 'image-a');
        Storage::disk('public')->put('site/logo.png', 'logo');
        Storage::disk('local')->put('lessons/v.mp4', 'video');
        // Never moved: probes and local backup archives.
        Storage::disk('public')->put('connection-tests/t.txt', 'x');
        Storage::disk('local')->put('backups/1/database.zip', 'zip');
        Storage::disk('public')->put('.gitignore', '*');

        $migration = $this->migrator()->runToCompletion($this->migrator()->start($this->cloud));

        $this->assertSame(StorageMigration::STATUS_COMPLETED, $migration->status);
        $this->assertSame(3, $migration->total);
        $this->assertSame(3, $migration->copied);

        Storage::disk('cloud-public')->assertExists(['library/a.jpg', 'site/logo.png']);
        Storage::disk('cloud-private')->assertExists('lessons/v.mp4');
        Storage::disk('cloud-public')->assertMissing(['connection-tests/t.txt', '.gitignore']);
        Storage::disk('cloud-private')->assertMissing('backups/1/database.zip');
        $this->assertSame('image-a', Storage::disk('cloud-public')->get('library/a.jpg'));

        // Source files are kept.
        Storage::disk('public')->assertExists('library/a.jpg');
        $this->assertSame($this->cloud->id, app(StorageSettings::class)->activeConfigId());
    }

    public function test_files_already_on_the_target_are_skipped(): void
    {
        Storage::disk('public')->put('library/a.jpg', 'same');
        Storage::disk('cloud-public')->put('library/a.jpg', 'same');

        $migration = $this->migrator()->runToCompletion($this->migrator()->start($this->cloud));

        $this->assertSame(1, $migration->skipped);
        $this->assertSame(0, $migration->copied);
    }

    public function test_a_failed_copy_keeps_the_site_on_the_old_storage(): void
    {
        Storage::disk('public')->put('library/a.jpg', 'x');
        $migration = $this->migrator()->start($this->cloud);
        $this->migrator()->index($migration);

        // The file disappears after listing, so copying it fails.
        Storage::disk('public')->delete('library/a.jpg');

        $migration = $this->migrator()->runToCompletion($migration);

        $this->assertSame(StorageMigration::STATUS_FAILED, $migration->status);
        $this->assertSame(1, $migration->failed);
        $this->assertNotNull($migration->last_error);
        $this->assertNull(app(StorageSettings::class)->activeConfigId());
    }

    public function test_files_uploaded_during_the_copy_are_picked_up_before_switching(): void
    {
        Storage::disk('public')->put('library/a.jpg', 'x');
        $migration = $this->migrator()->start($this->cloud);
        $this->migrator()->index($migration);
        $this->migrator()->processBatch($migration, 100);

        Storage::disk('public')->put('library/late.jpg', 'late');
        $this->migrator()->finish($migration);

        Storage::disk('cloud-public')->assertExists('library/late.jpg');
        $this->assertSame(StorageMigration::STATUS_COMPLETED, $migration->fresh()->status);
    }

    public function test_only_one_migration_at_a_time_and_never_to_the_current_storage(): void
    {
        $this->migrator()->start($this->cloud);

        $this->expectException(RuntimeException::class);
        $this->migrator()->start(null);
    }

    public function test_cannot_migrate_to_where_files_already_are(): void
    {
        $this->expectException(RuntimeException::class);

        $this->migrator()->start(null);
    }

    public function test_the_queued_job_runs_the_whole_migration_in_batches(): void
    {
        config()->set('storage.migration_batch_size', 1);
        Storage::disk('public')->put('a.jpg', 'a');
        Storage::disk('public')->put('b.jpg', 'b');
        Storage::disk('local')->put('c.mp4', 'c');

        $migration = $this->migrator()->start($this->cloud);
        MigrateStorageFiles::dispatch($migration->id);

        $migration->refresh();
        $this->assertSame(StorageMigration::STATUS_COMPLETED, $migration->status);
        $this->assertSame(3, $migration->copied);
        $this->assertSame($this->cloud->id, app(StorageSettings::class)->activeConfigId());
    }

    public function test_artisan_command_moves_files_back_to_this_server(): void
    {
        app(StorageSettings::class)->setActiveConfigId($this->cloud->id);
        app(AppStorageManager::class)->refresh();
        Storage::disk('cloud-public')->put('library/a.jpg', 'from-cloud');

        $this->artisan('storage:switch', ['target' => 'local', '--sync' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists('library/a.jpg');
        $this->assertNull(app(StorageSettings::class)->activeConfigId());
    }

    public function test_artisan_command_rejects_an_unknown_account(): void
    {
        $this->artisan('storage:switch', ['target' => '999', '--sync' => true])->assertFailed();
    }
}
