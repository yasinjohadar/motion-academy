<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationLevel;
use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function makeNotification(User $user, array $attributes = []): AppNotification
    {
        return AppNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Backups\\BackupCompleted',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'category' => NotificationCategory::Backups->value,
            'level' => NotificationLevel::Success->value,
            'action_url' => '/admin/backups/1',
            'data' => ['title' => 'اكتملت النسخة', 'message' => 'تم بنجاح'],
            'read_at' => null,
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_their_notification_centre_without_any_permission(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);

        $this->actingAs($user)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Notifications/Index')
                ->has('items.data', 1)
                ->where('items.data.0.title', 'اكتملت النسخة')
                ->where('items.data.0.is_read', false)
            );
    }

    public function test_shared_prop_exposes_unread_count_and_recent_items(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);
        $this->makeNotification($user, ['read_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread_count', 1)
                ->has('notifications.items', 2)
                ->has('notifications.realtime.poll_interval')
            );
    }

    public function test_notifications_are_scoped_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = $this->makeNotification($owner);

        $this->actingAs($intruder)
            ->get(route('admin.notifications.index'))
            ->assertInertia(fn ($page) => $page->has('items.data', 0));

        // Marking another user's notification read must not succeed.
        $this->actingAs($intruder)
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.read', $notification->id))
            ->assertRedirect(route('admin.notifications.index'))
            ->assertSessionHas('error');

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_another_user_cannot_delete_your_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = $this->makeNotification($owner);

        $this->actingAs($intruder)
            ->from(route('admin.notifications.index'))
            ->delete(route('admin.notifications.destroy', $notification->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user);

        $this->actingAs($user)
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.read', $notification->id))
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);
        $this->makeNotification($user);

        $this->actingAs($user)
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.read-all'))
            ->assertSessionHas('success');

        $this->assertSame(0, AppNotification::query()->whereNull('read_at')->count());
    }

    public function test_marking_all_read_does_not_touch_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeNotification($user);
        $otherNotification = $this->makeNotification($other);

        $this->actingAs($user)
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.read-all'));

        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_user_can_delete_a_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user);

        $this->actingAs($user)
            ->from(route('admin.notifications.index'))
            ->delete(route('admin.notifications.destroy', $notification->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_can_delete_all_their_notifications_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeNotification($user);
        $otherNotification = $this->makeNotification($other);

        $this->actingAs($user)
            ->delete(route('admin.notifications.destroy-all'))
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
        $this->assertSame(1, AppNotification::query()->count());
    }

    public function test_index_can_be_filtered_by_read_state(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);
        $this->makeNotification($user, ['read_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.notifications.index', ['state' => 'unread']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        $this->actingAs($user)
            ->get(route('admin.notifications.index', ['state' => 'read']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_index_can_be_filtered_by_category_and_level(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);
        $this->makeNotification($user, [
            'category' => NotificationCategory::Blog->value,
            'level' => NotificationLevel::Danger->value,
        ]);

        $this->actingAs($user)
            ->get(route('admin.notifications.index', ['category' => NotificationCategory::Blog->value]))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        $this->actingAs($user)
            ->get(route('admin.notifications.index', ['level' => NotificationLevel::Danger->value]))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_user_can_view_preferences_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.notifications.preferences'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Notifications/Preferences')
                ->has('matrix', count(NotificationCategory::cases()))
                ->has('channels', count(NotificationChannel::cases()))
                ->has('availability')
            );
    }

    public function test_user_can_save_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.notifications.preferences.update'), [
                'preferences' => [
                    NotificationCategory::Backups->value => [
                        NotificationChannel::Mail->value => false,
                        NotificationChannel::Broadcast->value => true,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.notifications.preferences'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => NotificationCategory::Backups->value,
            'channel' => NotificationChannel::Mail->value,
            'enabled' => false,
        ]);
    }

    public function test_the_database_channel_preference_is_never_persisted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('admin.notifications.preferences.update'), [
            'preferences' => [
                NotificationCategory::Backups->value => [
                    NotificationChannel::Database->value => false,
                ],
            ],
        ]);

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'channel' => NotificationChannel::Database->value,
        ]);
    }

    public function test_preference_matrix_marks_the_database_channel_locked(): void
    {
        $matrix = NotificationPreference::matrixForUser(User::factory()->create());

        $databaseCell = collect($matrix[0]['channels'])
            ->firstWhere('channel', NotificationChannel::Database->value);

        $this->assertTrue($databaseCell['locked']);
        $this->assertTrue($databaseCell['enabled']);
    }
}
