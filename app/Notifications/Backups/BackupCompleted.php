<?php

namespace App\Notifications\Backups;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\Backup;
use App\Notifications\BaseNotification;

class BackupCompleted extends BaseNotification
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
        return 'اكتملت النسخة الاحتياطية';
    }

    public function message(object $notifiable): string
    {
        $parts = ["تم إنشاء النسخة «{$this->backup->name}» بنجاح."];

        if ($this->backup->file_size) {
            $parts[] = 'الحجم: '.$this->backup->humanFileSize().'.';
        }

        if ($this->backup->duration) {
            $parts[] = 'المدة: '.$this->backup->duration.' ثانية.';
        }

        return implode(' ', $parts);
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
