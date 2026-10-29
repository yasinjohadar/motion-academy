<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Services\Backup\Concerns\WritesBackupLog;
use App\Services\Storage\AppStorageManager;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupRestoreService
{
    use WritesBackupLog;

    public function __construct(
        private readonly AppStorageManager $storage,
        private readonly BackupCompressionService $compression,
        private readonly DatabaseBackupEngine $databaseEngine,
    ) {}

    public function restore(Backup $backup): void
    {
        $backup->refresh();
        $backup->loadMissing('storageConfig');

        if ($backup->status !== 'completed' || ! $backup->storage_path) {
            throw new RuntimeException('النسخة غير جاهزة للاسترجاع.');
        }

        // Preserve operational backup row so DB restore cannot overwrite progress UI.
        $backupSnapshot = $this->snapshotBackupRecord($backup);

        $this->setRestoreProgress($backup, 'pending', 5, 'queued');

        $workDir = rtrim((string) config('backup.pre_restore_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'restore_'.$backup->id.'_'.uniqid();
        $preDir = rtrim((string) config('backup.pre_restore_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'safety_'.$backup->id.'_'.uniqid();

        mkdir($workDir, 0755, true);
        mkdir($preDir, 0755, true);

        try {
            $this->setRestoreProgress($backup, 'running', 15, 'safety_snapshot');
            $this->log($backup, 'info', 'إنشاء لقطة أمان قبل الاسترجاع');
            $this->createSafetySnapshot($backup->backup_type, $preDir);

            $this->setRestoreProgress($backup, 'running', 40, 'downloading');
            $archiveLocal = $workDir.'/archive.zip';
            $this->log($backup, 'info', 'تنزيل الأرشيف من التخزين');
            $this->storage->downloadToLocal($backup->storageConfig, $backup->storage_path, $archiveLocal);

            $this->setRestoreProgress($backup, 'running', 60, 'extracting');
            $extractDir = $workDir.'/extracted';
            $this->compression->extract($archiveLocal, $extractDir);

            $this->setRestoreProgress($backup, 'running', 80, 'applying');
            $this->log($backup, 'info', 'تطبيق الاسترجاع: '.$backup->backup_type);

            match ($backup->backup_type) {
                'database' => $this->restoreDatabase($extractDir),
                'files' => $this->restoreFiles($extractDir),
                'full' => $this->restoreFull($extractDir),
                default => throw new RuntimeException('نوع الاسترجاع غير مدعوم'),
            };

            $this->rehydrateBackupRecord($backupSnapshot, [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'error_message' => null,
            ]);
            $backup->refresh();

            $this->setRestoreProgress($backup, 'completed', 100, 'completed');
            $this->log($backup, 'info', 'تم الاسترجاع بنجاح');
        } catch (\Throwable $e) {
            $this->rehydrateBackupRecord($backupSnapshot);
            $backup->refresh();

            $this->log($backup, 'error', 'فشل الاسترجاع: '.$e->getMessage());
            try {
                $this->setRestoreProgress($backup, 'running', 90, 'rollback');
                $this->rollbackFromSafety($backup->backup_type, $preDir);
                $this->log($backup, 'info', 'تمت محاولة الرجوع من لقطة الأمان');
            } catch (\Throwable $rollbackError) {
                $this->log($backup, 'error', 'فشل الرجوع من لقطة الأمان: '.$rollbackError->getMessage());
            }

            $this->setRestoreProgress($backup, 'failed', (int) (($backup->fresh()->metadata['restore']['progress'] ?? 0)), 'failed', $e->getMessage());

            throw $e;
        } finally {
            File::deleteDirectory($workDir);
            File::deleteDirectory($preDir);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotBackupRecord(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'name' => $backup->name,
            'type' => $backup->type,
            'backup_type' => $backup->backup_type,
            'storage_driver' => $backup->storage_driver,
            'storage_config_id' => $backup->storage_config_id,
            'storage_path' => $backup->storage_path,
            'file_path' => $backup->file_path,
            'file_size' => $backup->file_size,
            'compression_type' => $backup->compression_type,
            'status' => $backup->status,
            'progress' => $backup->progress,
            'stage' => $backup->stage,
            'started_at' => $backup->started_at,
            'completed_at' => $backup->completed_at,
            'duration' => $backup->duration,
            'error_message' => $backup->error_message,
            'metadata' => $backup->metadata,
            'retention_days' => $backup->retention_days,
            'expires_at' => $backup->expires_at,
            'schedule_id' => $backup->schedule_id,
            'created_by' => $backup->created_by,
            'created_at' => $backup->created_at,
            'updated_at' => $backup->updated_at,
            'deleted_at' => $backup->deleted_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $overrides
     */
    private function rehydrateBackupRecord(array $snapshot, array $overrides = []): void
    {
        $payload = array_merge($snapshot, $overrides);
        $id = (int) $payload['id'];
        unset($payload['id']);

        /** @var Backup|null $record */
        $record = Backup::query()->withTrashed()->find($id);
        if (! $record) {
            $record = new Backup;
            $record->id = $id;
        }

        $record->forceFill($payload);
        $record->save();
    }

    private function setRestoreProgress(
        Backup $backup,
        string $status,
        int $progress,
        string $stage,
        ?string $error = null,
    ): void {
        $meta = $backup->metadata ?? [];
        $meta['restore'] = [
            'status' => $status,
            'progress' => $progress,
            'stage' => $stage,
            'error' => $error,
            'updated_at' => now()->toDateTimeString(),
            'finished_at' => in_array($status, ['completed', 'failed'], true)
                ? now()->toDateTimeString()
                : ($meta['restore']['finished_at'] ?? null),
        ];

        $backup->update(['metadata' => $meta]);
        $backup->refresh();
    }

    private function createSafetySnapshot(string $type, string $preDir): void
    {
        if (in_array($type, ['database', 'full'], true)) {
            $dbDir = $preDir.'/db';
            mkdir($dbDir, 0755, true);
            // A throwaway model that only carries the engine's inputs — never saved.
            // forceFill, not make(): `id` is not fillable, so mass assignment dropped
            // it silently and the engine received a model with no id at all.
            $engineBackup = (new Backup)->forceFill([
                'id' => 0,
                'backup_type' => 'database',
            ]);
            $this->databaseEngine->createDump($engineBackup, $dbDir);
        }

        if (in_array($type, ['files', 'full'], true)) {
            $source = (string) config('backup.files_source');
            $target = $preDir.'/files';
            if (is_dir($source)) {
                File::copyDirectory($source, $target);
            } else {
                mkdir($target, 0755, true);
            }
        }
    }

    private function rollbackFromSafety(string $type, string $preDir): void
    {
        if (in_array($type, ['database', 'full'], true)) {
            $sql = $this->compression->findFile($preDir.'/db', 'sql');
            if (! $sql) {
                $zip = $this->compression->findFile($preDir.'/db', 'zip');
                if ($zip) {
                    $extract = $preDir.'/db_extracted';
                    $this->compression->extract($zip, $extract);
                    $sql = $this->compression->findFile($extract, 'sql');
                }
            }
            if ($sql) {
                $this->databaseEngine->restoreFromSqlFile($sql);
            }
        }

        if (in_array($type, ['files', 'full'], true) && is_dir($preDir.'/files')) {
            $dest = (string) config('backup.files_source');
            if (is_dir($dest)) {
                File::cleanDirectory($dest);
            } else {
                mkdir($dest, 0755, true);
            }
            File::copyDirectory($preDir.'/files', $dest);
        }
    }

    private function restoreDatabase(string $extractDir): void
    {
        $sql = $this->compression->findFile($extractDir, 'sql');
        if (! $sql) {
            throw new RuntimeException('لم يتم العثور على ملف database.sql داخل الأرشيف.');
        }

        $this->databaseEngine->restoreFromSqlFile($sql);
    }

    private function restoreFiles(string $extractDir): void
    {
        $dest = (string) config('backup.files_source');
        $source = is_dir($extractDir.'/files') ? $extractDir.'/files' : $extractDir;

        if (! is_dir($dest)) {
            mkdir($dest, 0755, true);
        } else {
            File::cleanDirectory($dest);
        }

        File::copyDirectory($source, $dest);

        $this->restorePrivateFiles($extractDir);
    }

    /**
     * Archives made since private uploads are included carry a private_files/
     * folder. Older archives don't, and leave the private folder untouched.
     */
    private function restorePrivateFiles(string $extractDir): void
    {
        $source = $extractDir.'/private_files';
        if (! is_dir($source)) {
            return;
        }

        $dest = (string) config('backup.private_files_source');
        $keep = (array) config('backup.private_files_exclude', []);
        File::ensureDirectoryExists($dest);

        foreach (File::directories($dest) as $dir) {
            if (! in_array(basename($dir), $keep, true)) {
                File::deleteDirectory($dir);
            }
        }
        foreach (File::files($dest) as $file) {
            if (! str_starts_with($file->getFilename(), '.')) {
                File::delete($file->getPathname());
            }
        }

        File::copyDirectory($source, $dest);
    }

    private function restoreFull(string $extractDir): void
    {
        $this->restoreDatabase($extractDir);

        if (is_dir($extractDir.'/files')) {
            $dest = (string) config('backup.files_source');
            if (! is_dir($dest)) {
                mkdir($dest, 0755, true);
            } else {
                File::cleanDirectory($dest);
            }
            File::copyDirectory($extractDir.'/files', $dest);
        }

        $this->restorePrivateFiles($extractDir);
    }
}
