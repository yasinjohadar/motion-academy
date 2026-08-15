<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppNotification>
 */
class AppNotificationFactory extends Factory
{
    protected $model = AppNotification::class;

    public function definition(): array
    {
        return [
            // Laravel's notifications table uses a UUID string key, not an increment.
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Backups\\BackupCompleted',
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'category' => NotificationCategory::Backups->value,
            'level' => NotificationLevel::Info->value,
            'action_url' => null,
            'data' => [
                'title' => 'إشعار تجريبي',
                'message' => 'نص الإشعار التجريبي',
            ],
            'read_at' => null,
        ];
    }

    /**
     * Named `forUser`, not `for` — Factory::for() already exists with a different
     * signature and redeclaring it is a fatal error.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function category(NotificationCategory $category): static
    {
        return $this->state(fn (): array => ['category' => $category->value]);
    }

    public function level(NotificationLevel $level): static
    {
        return $this->state(fn (): array => ['level' => $level->value]);
    }
}
