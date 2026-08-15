<?php

namespace App\Notifications\Backups;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\Backup;
use App\Notifications\BaseNotification;

class BackupRestoreFailed extends BaseNotification
{
    public function __construct(public Backup $backup, public ?string $reason = null)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Backups;
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Danger;
    }

    public function title(object $notifiable): string
    {
        return 'فشل الاسترجاع';
    }

    public function message(object $notifiable): string
    {
        $reason = $this->reason ?: data_get($this->backup->restoreState(), 'error');

        return filled($reason)
            ? "فشل الاسترجاع من النسخة «{$this->backup->name}»: {$reason}"
            : "فشل الاسترجاع من النسخة «{$this->backup->name}» لسبب غير معروف.";
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
