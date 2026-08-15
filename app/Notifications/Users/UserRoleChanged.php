<?php

namespace App\Notifications\Users;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\User;
use App\Notifications\BaseNotification;

class UserRoleChanged extends BaseNotification
{
    /**
     * @param  array<int, string>  $previousRoles
     * @param  array<int, string>  $currentRoles
     */
    public function __construct(
        public User $affectedUser,
        public array $previousRoles,
        public array $currentRoles,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Users;
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Warning;
    }

    public function title(object $notifiable): string
    {
        return $this->isSelf($notifiable) ? 'تم تغيير أدوارك' : 'تم تغيير أدوار مستخدم';
    }

    public function message(object $notifiable): string
    {
        $from = $this->previousRoles === [] ? 'بلا أدوار' : implode('، ', $this->previousRoles);
        $to = $this->currentRoles === [] ? 'بلا أدوار' : implode('، ', $this->currentRoles);

        if ($this->isSelf($notifiable)) {
            return "تغيّرت أدوارك من ({$from}) إلى ({$to}).";
        }

        return "تغيّرت أدوار {$this->affectedUser->name} من ({$from}) إلى ({$to}).";
    }

    public function actionUrl(object $notifiable): ?string
    {
        return $this->isSelf($notifiable)
            ? route('admin.profile.edit')
            : route('admin.users.edit', $this->affectedUser->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'user_id' => $this->affectedUser->id,
            'previous_roles' => $this->previousRoles,
            'current_roles' => $this->currentRoles,
        ];
    }

    protected function isSelf(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->id === $this->affectedUser->id;
    }
}
