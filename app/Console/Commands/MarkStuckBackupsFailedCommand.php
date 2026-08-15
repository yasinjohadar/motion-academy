<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class MarkStuckBackupsFailedCommand extends Command
{
    protected $signature = 'backups:mark-stuck-failed';

    protected $description = 'تعليمليم النسخ العالقة كفاشلة';

    public function handle(BackupService $backups): int
    {
        $count = $backups->markStuckFailed();
        $this->info("Marked {$count} stuck backup(s) as failed.");

        return self::SUCCESS;
    }
}
