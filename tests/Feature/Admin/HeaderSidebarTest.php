<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The header off-canvas used to be a vendored Blade partial full of invented English
 * demo data, included outside `@inertia` so it could never show anything real.
 *
 * These tests pin the two things that made it worthless: the fabricated content is gone,
 * and the panel now has real data available to it on first paint.
 */
class HeaderSidebarTest extends TestCase
{
    use RefreshDatabase;

    /** Names and copy shipped with the theme; none of these people exist. */
    private const VENDOR_FIXTURES = [
        'Madeleine',
        'Anthony',
        'Olivia',
        'New Websites is Created',
        'Prepare For the Next Project',
        'New product Launching',
    ];

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    /**
     * Mirrors NotificationCenterTest::makeNotification — AppNotification maps onto
     * Laravel's polymorphic `notifications` table, so there is no factory to lean on.
     *
     * @param  array<string, mixed>  $attributes
     */
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

    public function test_the_dashboard_no_longer_ships_invented_demo_data(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.dashboard'));

        $response->assertOk();

        foreach (self::VENDOR_FIXTURES as $fixture) {
            $response->assertDontSee($fixture, escape: false);
        }
    }

    public function test_the_vendored_partial_is_gone_for_good(): void
    {
        // Deleting the file is the fix; this fails loudly if it is ever restored from
        // the theme package during an update.
        $this->assertFileDoesNotExist(resource_path('views/partials/edu-offcanvas.blade.php'));
        $this->assertStringNotContainsString(
            'edu-offcanvas',
            file_get_contents(resource_path('views/app.blade.php'))
        );
    }

    public function test_the_panel_has_real_notifications_available_on_first_paint(): void
    {
        $user = $this->adminUser();

        $this->makeNotification($user);
        $this->makeNotification($user);

        // Shared globally, so the panel reads it with no request of its own.
        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('notifications.items', 2)
                ->has('notifications.unread_count')
                ->etc());
    }

    public function test_the_unread_count_reflects_reality(): void
    {
        $user = $this->adminUser();

        foreach (range(1, 3) as $ignored) {
            $this->makeNotification($user);
        }

        $this->makeNotification($user, ['read_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('notifications.unread_count', 3)->etc());
    }

    public function test_another_users_notifications_never_reach_the_panel(): void
    {
        $user = $this->adminUser();
        $this->makeNotification(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->has('notifications.items', 0)->etc());
    }
}
