<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune
                            {--read-days= : عدد أيام الاحتفاظ بالإشعارات المقروءة}
                            {--unread-days= : عدد أيام الاحتفاظ بالإشعارات غير المقروءة}';

    protected $description = 'حذف الإشعارات القديمة حسب مدد الاحتفاظ في config/notifications.php';

    public function handle(): int
    {
        $readDays = (int) ($this->option('read-days') ?? config('notifications.retention.read_days', 30));
        $unreadDays = (int) ($this->option('unread-days') ?? config('notifications.retention.unread_days', 180));

        $readDeleted = 0;
        $unreadDeleted = 0;

        if ($readDays > 0) {
            $readDeleted = AppNotification::query()
                ->whereNotNull('read_at')
                ->where('created_at', '<', now()->subDays($readDays))
                ->delete();
        }

        if ($unreadDays > 0) {
            $unreadDeleted = AppNotification::query()
                ->whereNull('read_at')
                ->where('created_at', '<', now()->subDays($unreadDays))
                ->delete();
        }

        $this->info("تم حذف {$readDeleted} إشعاراً مقروءاً و {$unreadDeleted} إشعاراً غير مقروء.");

        return self::SUCCESS;
    }
}
