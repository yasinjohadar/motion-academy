<?php

namespace Tests\Unit\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\EvolutionInstance;
use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\NotificationChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationChannelResolverTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationChannelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(NotificationChannelResolver::class);

        // Deterministic baseline: every optional channel unavailable unless a test
        // explicitly makes it available.
        config()->set('notifications.realtime.enabled', false);
        config()->set('mail.default', 'smtp');
    }

    protected function userWithPhone(?string $phone = '966500000000'): User
    {
        return User::factory()->create(['phone' => $phone]);
    }

    public function test_database_channel_is_always_included(): void
    {
        $user = $this->userWithPhone(null);

        $channels = $this->resolver->resolve($user, new ResolverTestNotification);

        $this->assertContains('database', $channels);
    }

    public function test_database_channel_cannot_be_disabled_by_preferences(): void
    {
        $user = $this->userWithPhone(null);

        // syncForUser refuses to store a non-optional channel, so write it directly
        // to prove the resolver ignores it even if a row somehow exists.
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Backups->value,
            'channel' => NotificationChannel::Database->value,
            'enabled' => false,
        ]);

        $channels = $this->resolver->resolve($user, new ResolverTestNotification);

        $this->assertContains('database', $channels);
    }

    public function test_mail_channel_is_dropped_when_no_active_smtp_setting_exists(): void
    {
        $user = $this->userWithPhone(null);

        $channels = $this->resolver->resolve($user, new ResolverTestNotification);

        $this->assertNotContains('mail', $channels);
    }

    public function test_mail_channel_is_used_when_an_active_smtp_setting_exists(): void
    {
        SmtpSetting::query()->create([
            'name' => 'Primary',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user',
            'password' => 'secret',
            'encryption' => 'tls',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'Test',
            'is_active' => true,
            'is_default' => true,
        ]);

        $channels = $this->resolver->resolve($this->userWithPhone(null), new ResolverTestNotification);

        $this->assertContains('mail', $channels);
    }

    public function test_whatsapp_channel_is_dropped_when_user_has_no_phone(): void
    {
        $this->connectedWhatsAppInstance();

        $channels = $this->resolver->resolve($this->userWithPhone(null), new ResolverTestNotification);

        $this->assertNotContains(WhatsAppChannel::class, $channels);
    }

    public function test_whatsapp_channel_is_dropped_when_no_instance_is_connected(): void
    {
        EvolutionInstance::query()->create([
            'instance_name' => 'offline',
            'connection_status' => 'close',
        ]);

        $channels = $this->resolver->resolve($this->userWithPhone(), new ResolverTestNotification);

        $this->assertNotContains(WhatsAppChannel::class, $channels);
    }

    public function test_whatsapp_channel_is_used_when_phone_and_connected_instance_exist(): void
    {
        $this->connectedWhatsAppInstance();

        $user = $this->userWithPhone();

        NotificationPreference::syncForUser($user, [
            NotificationCategory::Backups->value => [
                NotificationChannel::WhatsApp->value => true,
            ],
        ]);

        $channels = $this->resolver->resolve($user, new ResolverTestNotification);

        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_broadcast_channel_is_dropped_when_realtime_is_disabled(): void
    {
        config()->set('notifications.realtime.enabled', false);
        config()->set('broadcasting.default', 'reverb');

        $channels = $this->resolver->resolve($this->userWithPhone(), new ResolverTestNotification);

        $this->assertNotContains('broadcast', $channels);
    }

    public function test_broadcast_channel_is_dropped_when_broadcasting_driver_is_null(): void
    {
        config()->set('notifications.realtime.enabled', true);
        config()->set('broadcasting.default', 'null');

        $channels = $this->resolver->resolve($this->userWithPhone(), new ResolverTestNotification);

        $this->assertNotContains('broadcast', $channels);
    }

    public function test_broadcast_channel_is_used_when_realtime_is_enabled(): void
    {
        config()->set('notifications.realtime.enabled', true);
        config()->set('broadcasting.default', 'reverb');

        $channels = $this->resolver->resolve($this->userWithPhone(), new ResolverTestNotification);

        $this->assertContains('broadcast', $channels);
    }

    public function test_user_preference_can_disable_an_optional_channel(): void
    {
        config()->set('notifications.realtime.enabled', true);
        config()->set('broadcasting.default', 'reverb');

        $user = $this->userWithPhone();

        NotificationPreference::syncForUser($user, [
            NotificationCategory::Backups->value => [
                NotificationChannel::Broadcast->value => false,
            ],
        ]);

        $channels = $this->resolver->resolve($user, new ResolverTestNotification);

        $this->assertNotContains('broadcast', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_supported_channels_are_respected(): void
    {
        config()->set('notifications.realtime.enabled', true);
        config()->set('broadcasting.default', 'reverb');
        $this->connectedWhatsAppInstance();

        $channels = $this->resolver->resolve($this->userWithPhone(), new DatabaseOnlyNotification);

        $this->assertSame(['database'], $channels);
    }

    public function test_availability_reports_a_reason_for_each_unavailable_channel(): void
    {
        $availability = $this->resolver->availabilityFor($this->userWithPhone(null));

        $this->assertFalse($availability[NotificationChannel::Mail->value]['available']);
        $this->assertNotNull($availability[NotificationChannel::Mail->value]['reason']);

        $this->assertFalse($availability[NotificationChannel::WhatsApp->value]['available']);
        $this->assertSame(
            'لا يوجد رقم هاتف في ملف المستخدم.',
            $availability[NotificationChannel::WhatsApp->value]['reason']
        );

        $this->assertTrue($availability[NotificationChannel::Database->value]['available']);
    }

    protected function connectedWhatsAppInstance(): EvolutionInstance
    {
        return EvolutionInstance::query()->create([
            'instance_name' => 'primary',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);
    }
}

class ResolverTestNotification extends BaseNotification
{
    public function category(): NotificationCategory
    {
        return NotificationCategory::Backups;
    }

    public function title(object $notifiable): string
    {
        return 'عنوان';
    }

    public function message(object $notifiable): string
    {
        return 'نص';
    }
}

class DatabaseOnlyNotification extends ResolverTestNotification
{
    /**
     * @return array<int, NotificationChannel>
     */
    public function supportedChannels(): array
    {
        return [NotificationChannel::Database];
    }
}
