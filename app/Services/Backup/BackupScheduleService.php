<?php

namespace App\Services\Backup;

use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\BackupSchedule;

class BackupScheduleService
{
    public function runDueSchedules(): int
    {
        $count = 0;

        BackupSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->with('storageConfig')
            ->each(function (BackupSchedule $schedule) use (&$count) {
                $this->dispatchSchedule($schedule);
                $count++;
            });

        return $count;
    }

    public function dispatchSchedule(BackupSchedule $schedule): Backup
    {
        $backup = Backup::query()->create([
            'name' => $schedule->name.' '.now()->format('Y-m-d_H-i-s'),
            'type' => 'scheduled',
            'backup_type' => $schedule->backup_type,
            'storage_driver' => $schedule->storageConfig?->driver ?? 'local',
            'storage_config_id' => $schedule->storage_config_id,
            'compression_type' => 'zip',
            'status' => 'pending',
            'retention_days' => $schedule->retention_days,
            'schedule_id' => $schedule->id,
            'created_by' => $schedule->created_by,
            'expires_at' => now()->addDays($schedule->retention_days),
        ]);

        CreateBackupJob::dispatch($backup->id);

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $schedule->calculateNextRunAt(),
        ]);

        return $backup;
    }
}
