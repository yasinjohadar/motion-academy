<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('backup:cleanup-expired')->daily();

Schedule::command('backups:mark-stuck-failed')->hourly();

Schedule::command('notifications:prune')->daily();

Schedule::command('observability:prune')->daily();
