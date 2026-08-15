<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class CleanupExpiredBackupsCommand extends Command
{
    protected $signature = 'backup:cleanup-expired';

    protected $description = 'حذف النسخ الاحتياطية منتهية الصلاحية';

    public function handle(BackupService $backups): int
    {
        $count = $backups->cleanupExpired();
        $this->info("Deleted {$count} expired backup(s).");

        return self::SUCCESS;
    }
}
