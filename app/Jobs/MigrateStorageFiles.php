<?php

namespace App\Jobs;

use App\Models\StorageMigration;
use App\Services\Storage\StorageMigrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copies one batch of files to the new storage and re-queues itself until the
 * migration is done — many short jobs instead of one that could time out.
 */
class MigrateStorageFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $migrationId) {}

    public function handle(StorageMigrator $migrator): void
    {
        $migration = StorageMigration::query()->find($this->migrationId);

        if (! $migration || ! $migration->isInProgress()) {
            return;
        }

        if ($migration->status === StorageMigration::STATUS_PENDING) {
            $migrator->index($migration);
        }

        if ($migrator->processBatch($migration, (int) config('storage.migration_batch_size', 100))) {
            $migrator->finish($migration);

            return;
        }

        self::dispatch($this->migrationId);
    }

    public function failed(?\Throwable $exception): void
    {
        $migration = StorageMigration::query()->find($this->migrationId);

        if ($migration?->isInProgress()) {
            app(StorageMigrator::class)->fail(
                $migration,
                'توقفت عملية النقل: '.($exception?->getMessage() ?? 'خطأ غير معروف').' — ما زال الموقع يعمل على التخزين السابق.',
            );
        }
    }
}
