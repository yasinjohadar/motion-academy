<?php

namespace App\Notifications\Users;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\BaseNotification;

class UserCreated extends BaseNotification
{
    public function __construct(public User $createdUser)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Users;
    }

    public function title(object $notifiable): string
    {
        return 'مستخدم جديد';
    }

    public function message(object $notifiable): string
    {
        return "تم إنشاء حساب جديد: {$this->createdUser->name} ({$this->createdUser->email}).";
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.users.edit', $this->createdUser->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return ['user_id' => $this->createdUser->id];
    }
}
