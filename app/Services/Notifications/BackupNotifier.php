<?php

namespace App\Services\Notifications;

use App\Models\Backup;
use App\Notifications\Backups\BackupCompleted;
use App\Notifications\Backups\BackupFailed;
use App\Notifications\Backups\BackupRestoreCompleted;
use App\Notifications\Backups\BackupRestoreFailed;
use App\Notifications\BaseNotification;
use App\Support\NotificationRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * Single dispatch point for backup notifications.
 *
 * BackupService::createBackup() marks the backup failed and then rethrows, which means
 * CreateBackupJob::failed() also runs — so a naive notify() call in both places would
 * fire twice. Every send here is stamped into `metadata.notifications.{event}` and
 * skipped if already stamped, making the calls idempotent regardless of how many code
 * paths reach them.
 */
class BackupNotifier
{
    public function completed(Backup $backup): void
    {
        $this->dispatch($backup, 'completed', new BackupCompleted($backup));
    }

    public function failed(Backup $backup, ?string $reason = null): void
    {
        $this->dispatch($backup, 'failed', new BackupFailed($backup, $reason));
    }

    public function restoreCompleted(Backup $backup): void
    {
        $this->dispatch($backup, 'restore_completed', new BackupRestoreCompleted($backup));
    }

    public function restoreFailed(Backup $backup, ?string $reason = null): void
    {
        $this->dispatch($backup, 'restore_failed', new BackupRestoreFailed($backup, $reason));
    }

    protected function dispatch(Backup $backup, string $event, BaseNotification $notification): void
    {
        if ($this->alreadyNotified($backup, $event)) {
            return;
        }

        $this->stamp($backup, $event);

        $recipients = NotificationRecipients::forPermissionIncluding(
            'admin.backups.list',
            $backup->creator
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    protected function alreadyNotified(Backup $backup, string $event): bool
    {
        return filled(data_get($backup->metadata, "notifications.{$event}"));
    }

    protected function stamp(Backup $backup, string $event): void
    {
        $metadata = $backup->metadata ?? [];
        data_set($metadata, "notifications.{$event}", now()->toIso8601String());

        $backup->forceFill(['metadata' => $metadata])->saveQuietly();
    }
}
