<?php

namespace App\Console\Commands;

use App\Jobs\MigrateStorageFiles;
use App\Models\AppStorageConfig;
use App\Services\Storage\StorageMigrator;
use Illuminate\Console\Command;

/**
 * Moves every uploaded file to another storage and switches the site to it —
 * the same thing as the button on the storage screen, for servers without a
 * queue worker (--sync) or for scripting.
 */
class SwitchStorage extends Command
{
    protected $signature = 'storage:switch
        {target : "local" for this server, or the id of a cloud storage account}
        {--sync : Copy the files in this process instead of queueing the job}';

    protected $description = 'Copy all uploaded files to another storage, then switch the site to it';

    public function handle(StorageMigrator $migrator): int
    {
        $targetArg = (string) $this->argument('target');
        $target = null;

        if ($targetArg !== 'local') {
            $target = AppStorageConfig::query()->find((int) $targetArg);

            if (! $target) {
                $this->error("No storage account with id {$targetArg}.");

                return self::FAILURE;
            }
        }

        try {
            $migration = $migrator->start($target, null);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $label = $target?->name ?? 'this server';

        if (! $this->option('sync')) {
            MigrateStorageFiles::dispatch($migration->id);
            $this->info("Queued: copying files to {$label}. Follow progress on the storage screen.");

            return self::SUCCESS;
        }

        $this->info("Copying files to {$label}...");

        $migration = $migrator->runToCompletion($migration, function ($migration) {
            $done = $migration->copied + $migration->skipped + $migration->failed;
            $this->line("  {$done}/{$migration->total} (failed: {$migration->failed})");
        });

        if ($migration->status !== 'completed') {
            $this->error((string) $migration->last_error);

            return self::FAILURE;
        }

        $this->info("Done: {$migration->copied} copied, {$migration->skipped} already there. The site now uses {$label}.");

        return self::SUCCESS;
    }
}
