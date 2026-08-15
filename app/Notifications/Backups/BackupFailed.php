<?php

namespace App\Notifications\Backups;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\Backup;
use App\Notifications\BaseNotification;

class BackupFailed extends BaseNotification
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
        return 'فشلت النسخة الاحتياطية';
    }

    public function message(object $notifiable): string
    {
        $reason = $this->reason ?: $this->backup->error_message;

        return filled($reason)
            ? "فشل إنشاء النسخة «{$this->backup->name}»: {$reason}"
            : "فشل إنشاء النسخة «{$this->backup->name}» لسبب غير معروف.";
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
        return [
            'backup_id' => $this->backup->id,
            'backup_type' => $this->backup->backup_type,
        ];
    }
}
