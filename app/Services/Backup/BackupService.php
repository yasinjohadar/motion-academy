<?php

namespace App\Services\Backup;

use App\Models\AppStorageConfig;
use App\Models\Backup;
use App\Services\Backup\Concerns\WritesBackupLog;
use App\Services\Storage\AppStorageManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupService
{
    use WritesBackupLog;

    public function __construct(
        private readonly DatabaseBackupEngine $databaseEngine,
        private readonly BackupCompressionService $compression,
        private readonly AppStorageManager $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function createBackup(array $options): Backup
    {
        $backup = isset($options['backup_id'])
            ? Backup::query()->findOrFail($options['backup_id'])
            : null;

        $storageConfig = AppStorageConfig::query()->findOrFail($options['storage_config_id']);

        if (! $backup) {
            $backup = Backup::query()->create([
                'name' => $options['name'] ?? 'backup_'.now()->format('Y-m-d_H-i-s'),
                'type' => $options['type'] ?? 'manual',
                'backup_type' => $options['backup_type'] ?? 'database',
                'storage_driver' => $storageConfig->driver,
                'storage_config_id' => $storageConfig->id,
                'compression_type' => 'zip',
                'status' => 'pending',
                'retention_days' => (int) ($options['retention_days'] ?? 30),
                'created_by' => $options['created_by'] ?? null,
                'schedule_id' => $options['schedule_id'] ?? null,
            ]);
        }

        $backup->update([
            'status' => 'running',
            'progress' => 5,
            'stage' => 'starting',
            'started_at' => now(),
            'error_message' => null,
            'expires_at' => $backup->calculateExpiresAt(),
            'storage_driver' => $storageConfig->driver,
            'storage_config_id' => $storageConfig->id,
        ]);

        $this->log($backup, 'info', 'بدء عملية النسخ الاحتياطي');

        $tmpDir = rtrim((string) config('backup.temp_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backup->id;

        try {
            if (is_dir($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
            mkdir($tmpDir, 0755, true);

            $localArchive = match ($backup->backup_type) {
                'database' => $this->buildDatabaseBackup($backup, $tmpDir),
                'files' => $this->buildFilesBackup($backup, $tmpDir),
                'full' => $this->buildFullBackup($backup, $tmpDir),
                default => throw new RuntimeException('نوع النسخ غير مدعوم: '.$backup->backup_type),
            };

            $this->setProgress($backup, 70, 'uploading');
            $filename = basename($localArchive);
            $remotePath = "backups/{$backup->id}/{$filename}";

            if (! $this->storage->putFileOnConfig($storageConfig, $remotePath, $localArchive)) {
                throw new RuntimeException('فشل رفع ملف النسخة إلى التخزين السحابي.');
            }

            $size = filesize($localArchive) ?: 0;

            $backup->update([
                'storage_path' => $remotePath,
                'file_path' => $remotePath,
                'file_size' => $size,
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'completed_at' => now(),
                'duration' => $backup->started_at ? now()->diffInSeconds($backup->started_at) : null,
            ]);

            $this->log($backup, 'info', 'اكتملت النسخة الاحتياطية بنجاح');

            if ($storageConfig->driver !== 'local') {
                File::deleteDirectory($tmpDir);
            }

            $this->enforceMaxBackups($storageConfig);

            return $backup->fresh(['storageConfig', 'creator']);
        } catch (\Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'stage' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'duration' => $backup->started_at ? now()->diffInSeconds($backup->started_at) : null,
            ]);
            $this->log($backup, 'error', 'فشل النسخ: '.$e->getMessage());
            File::deleteDirectory($tmpDir);

            throw $e;
        }
    }

    public function download(Backup $backup): StreamedResponse
    {
        if ($backup->status !== 'completed' || ! $backup->storage_path || ! $backup->storageConfig) {
            throw new RuntimeException('النسخة غير جاهزة للتنزيل.');
        }

        $disk = $this->storage->diskForConfig($backup->storageConfig);
        $path = $backup->storage_path;
        $name = basename($path);

        return response()->streamDownload(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                return;
            }
            echo $disk->get($path);
        }, $name, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function deleteBackup(Backup $backup): void
    {
        if ($backup->storageConfig && $backup->storage_path) {
            $this->storage->deleteFromConfig($backup->storageConfig, $backup->storage_path);
        }

        $tmpDir = rtrim((string) config('backup.temp_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backup->id;
        if (is_dir($tmpDir)) {
            File::deleteDirectory($tmpDir);
        }

        $backup->delete();
    }

    public function cleanupExpired(): int
    {
        $count = 0;
        Backup::query()
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (Backup $backup) use (&$count) {
                $this->deleteBackup($backup);
                $count++;
            });

        return $count;
    }

    public function markStuckFailed(): int
    {
        $minutes = (int) config('backup.stuck_running_minutes', 120);
        $count = 0;

        Backup::query()
            ->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->each(function (Backup $backup) use (&$count) {
                $backup->update([
                    'status' => 'failed',
                    'stage' => 'stuck',
                    'error_message' => 'توقفت العملية بسبب انتهاء المهلة.',
                    'completed_at' => now(),
                ]);
                $this->log($backup, 'error', 'تم تعليم النسخة كفاشلة (stuck)');
                $count++;
            });

        return $count;
    }

    private function buildDatabaseBackup(Backup $backup, string $tmpDir): string
    {
        $this->setProgress($backup, 20, 'dumping_database');
        $this->log($backup, 'info', 'جاري تصدير قاعدة البيانات');

        return $this->databaseEngine->createDump($backup, $tmpDir);
    }

    private function buildFilesBackup(Backup $backup, string $tmpDir): string
    {
        $this->setProgress($backup, 25, 'copying_files');
        $this->log($backup, 'info', 'جاري نسخ ملفات التخزين العام');

        $source = (string) config('backup.files_source');
        $stage = $tmpDir.'/files';
        if (! is_dir($source)) {
            mkdir($source, 0755, true);
        }
        File::copyDirectory($source, $stage);

        $this->setProgress($backup, 55, 'compressing');

        return $this->compression->zipDirectory($stage, $tmpDir.'/files.zip');
    }

    private function buildFullBackup(Backup $backup, string $tmpDir): string
    {
        $this->setProgress($backup, 15, 'dumping_database');
        $dbZip = $this->databaseEngine->createDump($backup, $tmpDir.'/db');
        $sqlExtractDir = $tmpDir.'/staging';
        mkdir($sqlExtractDir, 0755, true);
        $this->compression->extract($dbZip, $sqlExtractDir);

        $this->setProgress($backup, 40, 'copying_files');
        $source = (string) config('backup.files_source');
        $filesDir = $sqlExtractDir.'/files';
        if (is_dir($source)) {
            File::copyDirectory($source, $filesDir);
        } else {
            mkdir($filesDir, 0755, true);
        }

        $this->setProgress($backup, 60, 'compressing');
        $this->log($backup, 'info', 'جاري ضغط النسخة الكاملة');

        return $this->compression->zipDirectory($sqlExtractDir, $tmpDir.'/full.zip');
    }

    private function enforceMaxBackups(AppStorageConfig $config): void
    {
        $max = $config->max_backups;
        if (! $max || $max < 1) {
            return;
        }

        $extras = Backup::query()
            ->where('storage_config_id', $config->id)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->skip($max)
            ->take(100)
            ->get();

        foreach ($extras as $backup) {
            $this->deleteBackup($backup);
        }
    }

    private function setProgress(Backup $backup, int $progress, string $stage): void
    {
        $backup->update([
            'progress' => $progress,
            'stage' => $stage,
        ]);
    }
}
