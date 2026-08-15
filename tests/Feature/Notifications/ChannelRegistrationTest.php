<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Support\NotificationChannelResolver;
use App\Support\Notifications\ReportsChannelAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Stands in for a module channel that knows it cannot deliver. */
class UnavailableTestChannel implements ReportsChannelAvailability
{
    public function isAvailableFor(object $notifiable): bool
    {
        return false;
    }

    public function unavailableReasonFor(object $notifiable): ?string
    {
        return 'قناة الاختبار غير جاهزة.';
    }
}

/** Stands in for a module channel that does not implement the contract at all. */
class SilentTestChannel
{
    public function send(object $notifiable, object $notification): void {}
}

/**
 * Channels supplied by a module are resolved through config, not imported by the core.
 *
 * The behaviour that matters: uninstalling a module — which means removing its line
 * from `config/notifications.drivers` — must make its channel disappear cleanly rather
 * than blow up in the container when a notification is dispatched.
 */
class ChannelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function resolver(): NotificationChannelResolver
    {
        return app(NotificationChannelResolver::class);
    }

    public function test_built_in_channels_resolve_by_name(): void
    {
        $this->assertSame('database', NotificationChannel::Database->driver());
        $this->assertSame('broadcast', NotificationChannel::Broadcast->driver());
        $this->assertSame('mail', NotificationChannel::Mail->driver());
    }

    public function test_module_channels_resolve_through_config(): void
    {
        $this->assertSame(
            config('notifications.drivers.whatsapp'),
            NotificationChannel::WhatsApp->driver()
        );

        $this->assertTrue(NotificationChannel::WhatsApp->isRegistered());
    }

    public function test_removing_a_module_makes_its_channel_unregistered(): void
    {
        config()->set('notifications.drivers.whatsapp', null);

        $this->assertSame('', NotificationChannel::WhatsApp->driver());
        $this->assertFalse(NotificationChannel::WhatsApp->isRegistered());
    }

    public function test_an_unregistered_channel_is_never_offered_for_delivery(): void
    {
        $user = User::factory()->create(['phone' => '966511111111']);

        config()->set('notifications.drivers.whatsapp', null);

        // Otherwise a project that dropped the WhatsApp module would queue sends that
        // can only fail when the container tries to build a class that is gone.
        $this->assertFalse(
            $this->resolver()->isAvailable(NotificationChannel::WhatsApp, $user)
        );
    }

    public function test_a_class_named_in_config_but_missing_counts_as_unregistered(): void
    {
        config()->set('notifications.drivers.whatsapp', 'App\\Gone\\DeletedChannel');

        // Half-removed module: the config line survived but the class did not.
        $this->assertFalse(NotificationChannel::WhatsApp->isRegistered());
    }

    public function test_a_channel_that_reports_itself_unavailable_is_dropped(): void
    {
        config()->set('notifications.drivers.whatsapp', UnavailableTestChannel::class);

        $user = User::factory()->create(['phone' => '966511111111']);

        // The mechanism is generic: any module's channel can veto its own delivery
        // without the core knowing anything about that module.
        $this->assertFalse($this->resolver()->isAvailable(NotificationChannel::WhatsApp, $user));

        $this->assertSame(
            'قناة الاختبار غير جاهزة.',
            $this->resolver()->availabilityFor($user)['whatsapp']['reason']
        );
    }

    public function test_a_channel_with_no_opinion_is_available_once_registered(): void
    {
        config()->set('notifications.drivers.whatsapp', SilentTestChannel::class);

        $user = User::factory()->create(['phone' => '966511111111']);

        // Not implementing the contract must not mean "unavailable" — the core would
        // then be guessing on behalf of a module it does not understand.
        $this->assertTrue($this->resolver()->isAvailable(NotificationChannel::WhatsApp, $user));
    }

    public function test_built_in_channels_stay_available_when_module_config_is_empty(): void
    {
        config()->set('notifications.drivers', []);

        $user = User::factory()->create();

        // Control: dropping every module must not disturb the core channels.
        $this->assertTrue($this->resolver()->isAvailable(NotificationChannel::Database, $user));
        $this->assertTrue(NotificationChannel::Mail->isRegistered());
    }
}
