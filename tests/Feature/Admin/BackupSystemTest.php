<?php

namespace Tests\Feature\Admin;

use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\User;
use App\Services\Backup\BackupRestoreService;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseBackupEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.backups.list',
            'admin.backups.create',
            'admin.backups.show',
            'admin.backups.download',
            'admin.backups.restore',
            'admin.backups.delete',
            'admin.backup-schedules.list',
            'admin.backup-schedules.create',
            'admin.backup-schedules.edit',
            'admin.backup-schedules.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());

        Storage::fake('local');
        Storage::fake('public');
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_create_database_backup_to_local_storage(): void
    {
        $admin = $this->adminUser();

        // Sync queue runs the job immediately in testing
        $this->actingAs($admin)
            ->post(route('admin.backups.store'), [
                'name' => 'Test DB Backup',
                'backup_type' => 'database',
                'storage_config_id' => 'local',
                'retention_days' => 7,
            ])
            ->assertRedirect();

        $backup = Backup::query()->first();
        $this->assertNotNull($backup);
        $this->assertSame('completed', $backup->status);
        $this->assertNotNull($backup->storage_path);
        $this->assertGreaterThan(0, $backup->file_size);

        // Local backups live in the private folder, never under the public /storage link.
        $this->assertNull($backup->storage_config_id);
        Storage::disk('local')->assertExists($backup->storage_path);
        Storage::disk('public')->assertMissing($backup->storage_path);
    }

    public function test_admin_can_download_completed_backup(): void
    {
        $admin = $this->adminUser();

        $backup = Backup::query()->create([
            'name' => 'DL',
            'type' => 'manual',
            'backup_type' => 'database',
            'storage_driver' => 'local',
            'storage_config_id' => null,
            'status' => 'pending',
            'retention_days' => 7,
            'created_by' => $admin->id,
        ]);

        (new CreateBackupJob($backup->id))->handle(app(BackupService::class));
        $backup->refresh();

        $this->actingAs($admin)
            ->get(route('admin.backups.download', $backup))
            ->assertOk();
    }

    public function test_restore_requires_confirmation(): void
    {
        $admin = $this->adminUser();

        $backup = Backup::query()->create([
            'name' => 'Restore Me',
            'type' => 'manual',
            'backup_type' => 'database',
            'storage_driver' => 'local',
            'storage_config_id' => null,
            'status' => 'pending',
            'retention_days' => 7,
        ]);

        (new CreateBackupJob($backup->id))->handle(app(BackupService::class));
        $backup->refresh();

        $this->actingAs($admin)
            ->from(route('admin.backups.show', $backup))
            ->post(route('admin.backups.restore', $backup), [])
            ->assertSessionHasErrors('confirm');
    }

    public function test_restore_service_replays_database_backup(): void
    {

        $backup = Backup::query()->create([
            'name' => 'Restore Service',
            'type' => 'manual',
            'backup_type' => 'database',
            'storage_driver' => 'local',
            'storage_config_id' => null,
            'status' => 'pending',
            'retention_days' => 7,
        ]);

        (new CreateBackupJob($backup->id))->handle(app(BackupService::class));
        $backup->refresh();
        $this->assertSame('completed', $backup->status);

        app(BackupRestoreService::class)->restore($backup->fresh(['storageConfig']));

        $backup->refresh();
        $this->assertDatabaseHas('backups', ['id' => $backup->id, 'name' => 'Restore Service']);
        $this->assertSame('completed', $backup->status);
        $this->assertSame(100, (int) $backup->progress);
        $this->assertSame('completed', $backup->metadata['restore']['status'] ?? null);
    }

    public function test_database_dump_excludes_operational_backup_tables(): void
    {
        $tmp = storage_path('app/backups/tmp/exclude_test_'.uniqid());
        mkdir($tmp, 0755, true);

        $zipPath = app(DatabaseBackupEngine::class)->createDump(
            Backup::query()->make(['backup_type' => 'database']),
            $tmp,
        );

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $sql = $zip->getFromName('database.sql');
        $zip->close();

        $this->assertIsString($sql);
        $this->assertStringNotContainsString('INSERT INTO "backups"', $sql);
        $this->assertStringNotContainsString('INSERT INTO `backups`', $sql);
        $this->assertStringNotContainsString('INSERT INTO "backup_logs"', $sql);
        $this->assertStringNotContainsString('INSERT INTO `backup_logs`', $sql);
        $this->assertStringNotContainsString('CREATE TABLE "backups"', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `backups`', $sql);

        File::deleteDirectory($tmp);
    }

    public function test_admin_can_create_backup_schedule(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.backup-schedules.store'), [
                'name' => 'Daily DB',
                'backup_type' => 'database',
                'storage_config_id' => 'local',
                'frequency' => 'daily',
                'time' => '03:30',
                'retention_days' => 14,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.backup-schedules.index'));

        $this->assertDatabaseHas('backup_schedules', [
            'name' => 'Daily DB',
            'frequency' => 'daily',
            'storage_config_id' => null,
        ]);

        $schedule = BackupSchedule::query()->first();
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_store_dispatches_create_backup_job(): void
    {
        Queue::fake();

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.backups.store'), [
                'backup_type' => 'database',
                'storage_config_id' => 'local',
            ])
            ->assertRedirect();

        Queue::assertPushed(CreateBackupJob::class);
    }
}
