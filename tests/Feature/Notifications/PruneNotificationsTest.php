<?php

namespace Tests\Feature\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function notification(User $user, \DateTimeInterface $createdAt, bool $read): AppNotification
    {
        $notification = AppNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Backups\\BackupCompleted',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'category' => 'backups',
            'level' => 'info',
            'data' => ['title' => 'عنوان', 'message' => 'نص'],
            'read_at' => $read ? $createdAt : null,
        ]);

        // created_at is not fillable on DatabaseNotification, so backdate it directly.
        AppNotification::query()->whereKey($notification->id)->update(['created_at' => $createdAt]);

        return $notification->refresh();
    }

    public function test_old_read_notifications_are_pruned(): void
    {
        $user = User::factory()->create();
        config()->set('notifications.retention.read_days', 30);

        $old = $this->notification($user, now()->subDays(40), read: true);
        $recent = $this->notification($user, now()->subDays(5), read: true);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
    }

    public function test_unread_notifications_survive_the_read_retention_window(): void
    {
        $user = User::factory()->create();
        config()->set('notifications.retention.read_days', 30);
        config()->set('notifications.retention.unread_days', 180);

        $unreadButOld = $this->notification($user, now()->subDays(40), read: false);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['id' => $unreadButOld->id]);
    }

    public function test_very_old_unread_notifications_are_pruned(): void
    {
        $user = User::factory()->create();
        config()->set('notifications.retention.unread_days', 180);

        $ancient = $this->notification($user, now()->subDays(200), read: false);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $ancient->id]);
    }

    public function test_retention_windows_can_be_overridden_with_options(): void
    {
        $user = User::factory()->create();

        $read = $this->notification($user, now()->subDays(10), read: true);

        $this->artisan('notifications:prune', ['--read-days' => 5])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $read->id]);
    }

    public function test_zero_retention_disables_pruning_for_that_bucket(): void
    {
        $user = User::factory()->create();

        $read = $this->notification($user, now()->subDays(1000), read: true);

        $this->artisan('notifications:prune', ['--read-days' => 0, '--unread-days' => 0])
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['id' => $read->id]);
    }
}
