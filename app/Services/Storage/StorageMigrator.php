<?php

namespace App\Services\Storage;

use App\Models\AppStorageConfig;
use App\Models\StorageMigration;
use App\Models\StorageMigrationFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Copies every uploaded file to another storage, then switches the site to it.
 *
 * The site keeps running on the old storage until the very end, and only
 * switches when every file made it across — so no link ever points at a
 * storage that lacks its file. Source files are left in place.
 */
class StorageMigrator
{
    /** Never moved: temporary probes and backup archives (backups have their own destination). */
    private const IGNORED_PREFIXES = ['connection-tests/', 'backups/'];

    public function __construct(
        private readonly AppStorageManager $storage,
        private readonly StorageSettings $settings,
    ) {}

    /**
     * Opens a migration from the current storage to $target (null = this server).
     */
    public function start(?AppStorageConfig $target, ?int $userId = null): StorageMigration
    {
        if (StorageMigration::query()->whereIn('status', [StorageMigration::STATUS_PENDING, StorageMigration::STATUS_RUNNING])->exists()) {
            throw new RuntimeException('هناك عملية نقل جارية بالفعل. انتظر انتهاءها.');
        }

        $current = $this->storage->activeConfig();

        if ($current?->id === $target?->id) {
            throw new RuntimeException('الملفات مخزنة هناك بالفعل.');
        }

        if ($target && ! $target->is_active) {
            throw new RuntimeException('حساب التخزين المختار موقوف.');
        }

        return StorageMigration::query()->create([
            'from_config_id' => $current?->id,
            'to_config_id' => $target?->id,
            'status' => StorageMigration::STATUS_PENDING,
            'created_by' => $userId,
        ]);
    }

    /**
     * Lists the files to copy. Safe to call again: already-listed paths are skipped.
     */
    public function index(StorageMigration $migration): void
    {
        $migration->update([
            'status' => StorageMigration::STATUS_RUNNING,
            'started_at' => $migration->started_at ?? now(),
        ]);

        $this->listNewFiles($migration);
    }

    /**
     * Copies up to $limit pending files. Returns true when none are left.
     */
    public function processBatch(StorageMigration $migration, int $limit): bool
    {
        $files = $migration->files()->where('status', 'pending')->orderBy('id')->limit($limit)->get();

        foreach ($files as $file) {
            $this->copyFile($migration, $file);
        }

        return ! $migration->files()->where('status', 'pending')->exists();
    }

    /**
     * Picks up files uploaded while copying, then switches — or fails without
     * switching when anything could not be copied.
     */
    public function finish(StorageMigration $migration): void
    {
        if ($this->listNewFiles($migration) > 0) {
            while (! $this->processBatch($migration, 500)) {
                // keep copying the late arrivals
            }
        }

        $migration->refresh();

        if ($migration->failed > 0) {
            $this->fail($migration, "تعذّر نسخ {$migration->failed} ملف. لم يتم التبديل، وما زال الموقع يعمل على التخزين السابق. يمكنك إعادة المحاولة.");

            return;
        }

        $this->settings->setActiveConfigId($migration->to_config_id);
        $this->storage->refresh();

        $migration->update([
            'status' => StorageMigration::STATUS_COMPLETED,
            'finished_at' => now(),
            'last_error' => null,
        ]);
    }

    public function fail(StorageMigration $migration, string $message): void
    {
        $migration->update([
            'status' => StorageMigration::STATUS_FAILED,
            'finished_at' => now(),
            'last_error' => Str::limit($message, 2000),
        ]);
    }

    /**
     * Runs a whole migration in this process (artisan --sync, or a sync queue).
     */
    public function runToCompletion(StorageMigration $migration, ?callable $onProgress = null): StorageMigration
    {
        try {
            $this->index($migration);

            while (! $this->processBatch($migration, (int) config('storage.migration_batch_size', 100))) {
                if ($onProgress) {
                    $onProgress($migration->refresh());
                }
            }

            $this->finish($migration);
        } catch (\Throwable $e) {
            $this->fail($migration, $e->getMessage());
        }

        return $migration->refresh();
    }

    private function listNewFiles(StorageMigration $migration): int
    {
        $source = $migration->fromConfig;
        $added = 0;

        foreach (['public', 'private'] as $zone) {
            $disk = $this->storage->diskForTarget($source, $zone);
            $known = $migration->files()->where('zone', $zone)->pluck('path')->flip();

            $rows = [];
            foreach ($disk->allFiles() as $path) {
                if (isset($known[$path]) || $this->ignored($path)) {
                    continue;
                }

                $rows[] = [
                    'storage_migration_id' => $migration->id,
                    'zone' => $zone,
                    'path' => $path,
                    'status' => 'pending',
                ];

                if (count($rows) === 500) {
                    StorageMigrationFile::query()->insert($rows);
                    $added += count($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                StorageMigrationFile::query()->insert($rows);
                $added += count($rows);
            }
        }

        if ($added > 0) {
            $migration->increment('total', $added);
        }

        return $added;
    }

    private function copyFile(StorageMigration $migration, StorageMigrationFile $file): void
    {
        $source = $this->storage->diskForTarget($migration->fromConfig, $file->zone);
        $target = $this->storage->diskForTarget($migration->toConfig, $file->zone);

        try {
            if ($target->exists($file->path) && $target->size($file->path) === $source->size($file->path)) {
                $file->update(['status' => 'skipped']);
                $migration->increment('skipped');

                return;
            }

            $this->stream($source, $target, $file->path);

            $file->update(['status' => 'copied']);
            $migration->increment('copied');
        } catch (\Throwable $e) {
            $file->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);
            $migration->increment('failed');
            $migration->update(['last_error' => Str::limit("{$file->path}: {$e->getMessage()}", 2000)]);
        }
    }

    private function stream(Filesystem $source, Filesystem $target, string $path): void
    {
        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('تعذّرت قراءة الملف من التخزين الحالي.');
        }

        try {
            if (! $target->writeStream($path, $stream)) {
                throw new RuntimeException('تعذّرت كتابة الملف في التخزين الجديد.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function ignored(string $path): bool
    {
        return Str::startsWith($path, self::IGNORED_PREFIXES) || Str::startsWith(basename($path), '.');
    }
}
