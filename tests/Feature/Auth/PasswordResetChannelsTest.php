<?php

namespace Tests\Feature\Auth;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Notifications\WhatsAppChannel;
use App\Modules\WhatsApp\Services\SendWhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\BaseNotification;
use App\Support\NotificationChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The resolver refuses to queue a channel that cannot deliver, so give the
        // WhatsApp side something real to point at.
        EvolutionInstance::query()->create([
            'instance_name' => 'test-instance',
            'connection_status' => 'open',
        ]);
    }

    protected function userWithPhone(): User
    {
        return User::factory()->create(['phone' => '966511111111']);
    }

    // ------------------------------------------------------------------ routing

    public function test_the_reset_link_goes_out_on_mail_and_whatsapp(): void
    {
        Notification::fake();

        $user = $this->userWithPhone();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class,
            function ($notification, array $channels): bool {
                return count($channels) === 2
                    && in_array('mail', $channels, true)
                    && in_array(WhatsAppChannel::class, $channels, true);
            });
    }

    public function test_a_user_without_a_phone_still_gets_the_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['phone' => null]);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class,
            fn ($notification, array $channels): bool => $channels === ['mail']);
    }

    public function test_the_link_is_never_stored_in_app_or_pushed(): void
    {
        $user = $this->userWithPhone();
        $channels = (new ResetPasswordNotification('tok'))->via($user);

        // A reset token in `notifications.data` is a working credential sitting in a
        // table the admin screens read.
        $this->assertNotContains('database', $channels);
        $this->assertNotContains(NotificationChannel::WebPush->driver(), $channels);
    }

    public function test_muting_the_category_cannot_suppress_the_reset(): void
    {
        $user = $this->userWithPhone();

        foreach (NotificationChannel::cases() as $channel) {
            NotificationPreference::query()->create([
                'user_id' => $user->id,
                'category' => NotificationCategory::Users->value,
                'channel' => $channel->value,
                'enabled' => false,
            ]);
        }

        $channels = (new ResetPasswordNotification('tok'))->via($user);

        // Otherwise a toggle locks the user out of their own account permanently.
        $this->assertContains('mail', $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_preferences_still_govern_ordinary_notifications(): void
    {
        $user = $this->userWithPhone();

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Users->value,
            'channel' => NotificationChannel::Mail->value,
            'enabled' => false,
        ]);

        // Control: the bypass must be specific to this notification, not a hole in
        // the preference system.
        $ordinary = new class extends BaseNotification
        {
            public function category(): NotificationCategory
            {
                return NotificationCategory::Users;
            }

            public function title(object $notifiable): string
            {
                return 'عادي';
            }

            public function message(object $notifiable): string
            {
                return 'رسالة';
            }
        };

        $this->assertNotContains('mail', $ordinary->via($user));
    }

    // ------------------------------------------------------------------ content

    public function test_the_message_carries_a_usable_reset_link(): void
    {
        $user = $this->userWithPhone();
        $notification = new ResetPasswordNotification('the-token');

        $url = $notification->actionUrl($user);

        $this->assertStringContainsString('reset-password/the-token', $url);
        $this->assertStringContainsString(urlencode($user->email), $url);
        $this->assertStringContainsString($url, $notification->toWhatsApp($user));
        $this->assertSame($url, $notification->toMail($user)->actionUrl);
    }

    public function test_the_stated_expiry_matches_what_the_broker_enforces(): void
    {
        config()->set('auth.passwords.users.expire', 17);

        // A message promising 60 minutes on a 17-minute token trains users to distrust it.
        $this->assertStringContainsString('17 دقيقة',
            (new ResetPasswordNotification('tok'))->message($this->userWithPhone()));
    }

    // ------------------------------------------------------------------ whatsapp delivery

    public function test_the_whatsapp_send_is_tagged_with_the_password_reset_purpose(): void
    {
        Queue::fake();

        $user = $this->userWithPhone();

        (new WhatsAppChannel(app(SendWhatsAppMessage::class)))
            ->send($user, new ResetPasswordNotification('the-token'));

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job): bool {
            // The purpose is what selects the approved template once Meta's 24-hour
            // window has closed — which it always has for someone requesting a reset.
            return ($job->messageData['purpose'] ?? null) === 'password_reset'
                && ($job->messageData['template_parameters'] ?? []) === ['the-token'];
        });
    }

    // ------------------------------------------------------------------ availability

    public function test_whatsapp_stays_available_when_the_provider_is_meta(): void
    {
        EvolutionInstance::query()->delete();

        app(WhatsAppSettingsService::class)->updateSettings([
            'whatsapp_provider' => 'meta',
            'meta_phone_number_id' => '123456',
            'meta_access_token' => 'tok',
        ]);

        // Meta installations have no Evolution instance at all; checking only for one
        // would silently disable WhatsApp for every notification.
        $this->assertTrue(app(NotificationChannelResolver::class)
            ->isAvailable(NotificationChannel::WhatsApp, $this->userWithPhone()));
    }

    public function test_whatsapp_is_unavailable_when_meta_is_not_configured(): void
    {
        EvolutionInstance::query()->delete();

        app(WhatsAppSettingsService::class)->updateSettings([
            'whatsapp_provider' => 'meta',
            'meta_phone_number_id' => '',
            'meta_access_token' => '',
        ]);

        $this->assertFalse(app(NotificationChannelResolver::class)
            ->isAvailable(NotificationChannel::WhatsApp, $this->userWithPhone()));
    }
}
