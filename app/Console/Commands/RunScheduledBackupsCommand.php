<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupScheduleService;
use Illuminate\Console\Command;

class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'backup:run-scheduled';

    protected $description = 'تشغيل جداول النسخ الاحتياطية المستحقة';

    public function handle(BackupScheduleService $schedules): int
    {
        $count = $schedules->runDueSchedules();
        $this->info("Dispatched {$count} scheduled backup(s).");

        return self::SUCCESS;
    }
}
