<?php

return [

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),
    'dump_timeout' => (int) env('BACKUP_DUMP_TIMEOUT', 3600),
    'php_fallback_enabled' => (bool) env('BACKUP_PHP_FALLBACK', true),
    'job_timeout' => (int) env('BACKUP_JOB_TIMEOUT', 3600),
    'stuck_running_minutes' => (int) env('BACKUP_STUCK_RUNNING_MINUTES', 120),
    'temp_path' => storage_path('app/backups/tmp'),
    'pre_restore_path' => storage_path('app/backups/pre_restore'),
    'files_source' => storage_path('app/public'),

    /*
    | When true, backup/restore jobs use the queue connection.
    | Set BACKUP_DISPATCH_SYNC=true to run immediately without queue:work.
    */
    'dispatch_sync' => (bool) env('BACKUP_DISPATCH_SYNC', false),

    /*
    | Tables excluded from DB dumps/restores so operational backup state
    | is never overwritten by an in-progress snapshot inside the archive.
    */
    'exclude_tables' => [
        'backups',
        'backup_logs',
        'backup_schedules',
        'ai_providers',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'cache',
        'cache_locks',
    ],

];
