<?php

namespace Tests\Feature\Notifications;

use App\Models\Backup;
use App\Models\User;
use App\Notifications\Backups\BackupCompleted;
use App\Notifications\Backups\BackupFailed;
use App\Notifications\Backups\BackupRestoreCompleted;
use App\Notifications\Backups\BackupRestoreFailed;
use App\Services\Notifications\BackupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('admin.backups.list', 'web');
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    protected function backup(array $attributes = []): Backup
    {
        return Backup::query()->create([
            'name' => 'backup_test',
            'type' => 'manual',
            'backup_type' => 'full',
            'storage_driver' => 'local',
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'completed',
            'file_size' => 2048,
            'duration' => 12,
            ...$attributes,
        ]);
    }

    protected function notifier(): BackupNotifier
    {
        return app(BackupNotifier::class);
    }

    public function test_completion_notifies_users_who_can_see_backups(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $unrelated = User::factory()->create();

        $this->notifier()->completed($this->backup());

        Notification::assertSentTo($admin, BackupCompleted::class);
        Notification::assertNotSentTo($unrelated, BackupCompleted::class);
    }

    public function test_completion_also_notifies_the_creator(): void
    {
        Notification::fake();

        $creator = User::factory()->create();

        $this->notifier()->completed($this->backup(['created_by' => $creator->id]));

        Notification::assertSentTo($creator, BackupCompleted::class);
    }

    public function test_failure_notification_carries_the_reason(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->notifier()->failed($this->backup(['status' => 'failed']), 'القرص ممتلئ');

        Notification::assertSentTo(
            $admin,
            BackupFailed::class,
            fn (BackupFailed $notification) => str_contains($notification->message($admin), 'القرص ممتلئ')
        );
    }

    public function test_a_notification_is_only_sent_once_per_backup_event(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $backup = $this->backup(['status' => 'failed']);

        // BackupService marks the backup failed and rethrows, so CreateBackupJob::failed()
        // runs too — both paths reaching the notifier must produce one notification.
        $this->notifier()->failed($backup, 'أول محاولة');
        $this->notifier()->failed($backup->refresh(), 'ثاني محاولة');

        Notification::assertSentToTimes($admin, BackupFailed::class, 1);
    }

    public function test_completion_and_failure_are_tracked_independently(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $backup = $this->backup();

        $this->notifier()->completed($backup);
        $this->notifier()->failed($backup->refresh(), 'خطأ لاحق');

        Notification::assertSentToTimes($admin, BackupCompleted::class, 1);
        Notification::assertSentToTimes($admin, BackupFailed::class, 1);
    }

    public function test_restore_notifications_are_dispatched_and_deduplicated(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $backup = $this->backup();

        $this->notifier()->restoreCompleted($backup);
        $this->notifier()->restoreCompleted($backup->refresh());
        $this->notifier()->restoreFailed($backup->refresh(), 'تعارض في المخطط');

        Notification::assertSentToTimes($admin, BackupRestoreCompleted::class, 1);
        Notification::assertSentToTimes($admin, BackupRestoreFailed::class, 1);
    }

    public function test_the_dedupe_stamp_is_written_to_backup_metadata(): void
    {
        Notification::fake();

        User::factory()->create()->assignRole('admin');
        $backup = $this->backup();

        $this->notifier()->completed($backup);

        $this->assertNotNull(data_get($backup->fresh()->metadata, 'notifications.completed'));
    }

    public function test_notification_payload_reaches_the_database_with_indexed_columns(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $backup = $this->backup();

        $this->notifier()->completed($backup);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'category' => 'backups',
            'level' => 'success',
        ]);
    }
}
