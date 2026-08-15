<?php

namespace App\Notifications\Backups;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\Backup;
use App\Notifications\BaseNotification;

class BackupRestoreCompleted extends BaseNotification
{
    public function __construct(public Backup $backup)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Backups;
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Success;
    }

    public function title(object $notifiable): string
    {
        return 'اكتمل الاسترجاع';
    }

    public function message(object $notifiable): string
    {
        return "تم استرجاع النظام من النسخة «{$this->backup->name}» بنجاح.";
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.backups.show', $this->backup->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return ['backup_id' => $this->backup->id];
    }
}
