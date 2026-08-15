<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Backup\BackupService;
use App\Services\Notifications\BackupNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $backupId) {}

    public function timeout(): int
    {
        return (int) config('backup.job_timeout', 3600);
    }

    public function handle(BackupService $backupService): void
    {
        $backup = Backup::query()->with('storageConfig')->findOrFail($this->backupId);

        $backupService->createBackup([
            'backup_id' => $backup->id,
            'storage_config_id' => $backup->storage_config_id,
            'backup_type' => $backup->backup_type,
            'name' => $backup->name,
            'type' => $backup->type,
            'retention_days' => $backup->retention_days,
            'created_by' => $backup->created_by,
            'schedule_id' => $backup->schedule_id,
        ]);

        app(BackupNotifier::class)->completed($backup->refresh());
    }

    public function failed(?\Throwable $exception): void
    {
        $backup = Backup::query()->find($this->backupId);
        if (! $backup) {
            return;
        }

        $backup->update([
            'status' => 'failed',
            'stage' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'فشل غير معروف',
            'completed_at' => now(),
        ]);

        Log::error('CreateBackupJob failed', [
            'backup_id' => $this->backupId,
            'error' => $exception?->getMessage(),
        ]);

        // Fired here rather than in BackupService so timeouts — where the service's
        // catch block never runs — are still reported.
        app(BackupNotifier::class)->failed($backup, $exception?->getMessage());
    }
}
