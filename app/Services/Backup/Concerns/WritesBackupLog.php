<?php

namespace App\Services\Backup\Concerns;

use App\Models\Backup;
use App\Models\BackupLog;

/**
 * Shared writer for the per-backup operation log.
 *
 * BackupService and BackupRestoreService each carried a byte-identical copy of this
 * method (one public, one private); this is that duplication removed.
 */
trait WritesBackupLog
{
    public function log(Backup $backup, string $level, string $message): void
    {
        BackupLog::query()->create([
            'backup_id' => $backup->id,
            'level' => $level,
            'message' => $message,
        ]);
    }
}
