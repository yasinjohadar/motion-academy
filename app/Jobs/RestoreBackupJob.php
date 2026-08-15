<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Backup\BackupRestoreService;
use App\Services\Notifications\BackupNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $backupId) {}

    public function timeout(): int
    {
        return (int) config('backup.job_timeout', 3600);
    }

    public function handle(BackupRestoreService $restoreService): void
    {
        $backup = Backup::query()->with('storageConfig')->findOrFail($this->backupId);
        $restoreService->restore($backup);

        app(BackupNotifier::class)->restoreCompleted($backup->refresh());
    }

    public function failed(?\Throwable $exception): void
    {
        $backup = Backup::query()->find($this->backupId);
        if (! $backup) {
            return;
        }

        $meta = $backup->metadata ?? [];
        $meta['restore'] = [
            'status' => 'failed',
            'progress' => (int) ($meta['restore']['progress'] ?? 0),
            'stage' => 'failed',
            'error' => $exception?->getMessage() ?? 'فشل غير معروف',
            'finished_at' => now()->toDateTimeString(),
        ];
        $backup->update(['metadata' => $meta]);

        Log::error('RestoreBackupJob failed', [
            'backup_id' => $this->backupId,
            'error' => $exception?->getMessage(),
        ]);

        // Fired here rather than in BackupRestoreService so timeouts — where the
        // service's catch block never runs — are still reported.
        app(BackupNotifier::class)->restoreFailed($backup, $exception?->getMessage());
    }
}
