<?php

namespace Tests\Feature\Architecture;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Services\Dashboard\DashboardStats;
use App\Support\NotificationChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The point of the whole exercise: a project that does not want WhatsApp can turn it
 * off and the core keeps working.
 *
 * Uninstalling means removing the module's registrations — its notification driver,
 * its OTP channel, its dashboard contributor. These tests simulate exactly that and
 * then exercise the core screens, because "the imports are gone" is only evidence;
 * "the dashboard still renders" is the actual guarantee.
 */
class ModuleRemovalTest extends TestCase
{
    use RefreshDatabase;

    /** Everything a project would strip out when dropping the module. */
    protected function uninstallWhatsApp(): void
    {
        config()->set('notifications.drivers.whatsapp', null);
        config()->set('otp.channel', null);
        config()->set('dashboard.contributors', []);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    public function test_the_dashboard_still_renders_without_the_module(): void
    {
        $this->uninstallWhatsApp();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_dashboard_stats_drop_the_module_tiles_and_keep_the_core_ones(): void
    {
        $this->uninstallWhatsApp();

        $stats = app(DashboardStats::class);
        $keys = array_column($stats->overview(), 'key');

        $this->assertContains('users', $keys);
        $this->assertContains('health', $keys);
        $this->assertNotContains('messages', $keys, 'A removed module still shows its tile.');
    }

    public function test_system_health_stays_scorable_without_module_checks(): void
    {
        $this->uninstallWhatsApp();

        $health = app(DashboardStats::class)->systemHealth();

        // The score divides by the number of checks — dropping every module check must
        // not leave that at zero.
        $this->assertIsInt($health['score']);
        $this->assertGreaterThanOrEqual(0, $health['score']);
        $this->assertNotEmpty($health['checks']);
    }

    public function test_the_whatsapp_channel_disappears_from_notifications(): void
    {
        $this->uninstallWhatsApp();

        $user = User::factory()->create(['phone' => '966511111111']);

        $this->assertFalse(
            app(NotificationChannelResolver::class)->isAvailable(NotificationChannel::WhatsApp, $user)
        );
    }

    public function test_the_preferences_screen_still_loads_without_the_module(): void
    {
        $this->uninstallWhatsApp();

        // This screen asks every channel why it is unavailable — a removed module must
        // produce a message, not an exception.
        $this->actingAs($this->admin())
            ->get(route('admin.notifications.preferences'))
            ->assertOk();
    }

    public function test_password_reset_still_reaches_the_user_by_mail(): void
    {
        $this->uninstallWhatsApp();

        $user = User::factory()->create(['phone' => '966511111111']);

        // WhatsApp was one of two channels for this; losing it must not lose the reset.
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(
            DB::table('password_reset_tokens')
                ->where('email', $user->email)->first()
        );
    }
}
