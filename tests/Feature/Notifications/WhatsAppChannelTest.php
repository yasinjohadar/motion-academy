<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\EvolutionInstance;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Notifications\BaseNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\WhatsApp\WhatsAppMessageReceivedNotification;
use App\Services\WhatsApp\SendWhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EvolutionInstance::query()->create([
            'instance_name' => 'primary',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);
    }

    protected function userOptedIntoWhatsApp(): User
    {
        $user = User::factory()->create(['phone' => '966500000000']);

        NotificationPreference::syncForUser($user, [
            NotificationCategory::Backups->value => [
                NotificationChannel::WhatsApp->value => true,
            ],
        ]);

        return $user;
    }

    public function test_the_channel_sends_the_notification_text_through_the_existing_send_service(): void
    {
        $user = $this->userOptedIntoWhatsApp();

        $service = Mockery::mock(SendWhatsAppMessage::class);
        $service->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $to, string $text): bool {
                return $to === '966500000000'
                    && str_contains($text, 'عنوان الاختبار')
                    && str_contains($text, 'نص الاختبار');
            })
            ->andReturn(new WhatsAppMessage);

        $this->app->instance(SendWhatsAppMessage::class, $service);

        (new WhatsAppChannel($service))->send($user, new WhatsAppTestNotification);
    }

    public function test_the_channel_is_skipped_when_the_user_has_no_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $service = Mockery::mock(SendWhatsAppMessage::class);
        $service->shouldNotReceive('sendText');

        (new WhatsAppChannel($service))->send($user, new WhatsAppTestNotification);
    }

    public function test_a_send_failure_does_not_bubble_out_of_the_channel(): void
    {
        $user = $this->userOptedIntoWhatsApp();

        $service = Mockery::mock(SendWhatsAppMessage::class);
        $service->shouldReceive('sendText')->once()->andThrow(new \RuntimeException('API down'));

        // The database record is already written by this point, so a dead WhatsApp
        // instance must not fail the whole notification.
        (new WhatsAppChannel($service))->send($user, new WhatsAppTestNotification);

        $this->assertTrue(true);
    }

    public function test_inbound_whatsapp_notification_never_goes_back_out_over_whatsapp(): void
    {
        $contact = WhatsAppContact::findOrCreateByWaId('966500000000');
        $message = WhatsAppMessage::query()->create([
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => 'مرحباً',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        $notification = new WhatsAppMessageReceivedNotification($message);

        $this->assertNotContains(NotificationChannel::WhatsApp, $notification->supportedChannels());

        // And confirm it survives channel resolution too — a preference cannot re-enable it.
        $user = $this->userOptedIntoWhatsApp();
        NotificationPreference::syncForUser($user, [
            NotificationCategory::WhatsApp->value => [
                NotificationChannel::WhatsApp->value => true,
            ],
        ]);

        $this->assertNotContains(WhatsAppChannel::class, $notification->via($user));
    }

    public function test_inbound_whatsapp_notification_is_still_stored_in_app(): void
    {
        $contact = WhatsAppContact::findOrCreateByWaId('966500000000');
        $message = WhatsAppMessage::query()->create([
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => 'مرحباً',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        $user = User::factory()->create();
        $user->notify(new WhatsAppMessageReceivedNotification($message));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'category' => 'whatsapp',
        ]);
    }
}

class WhatsAppTestNotification extends BaseNotification
{
    public function category(): NotificationCategory
    {
        return NotificationCategory::Backups;
    }

    public function title(object $notifiable): string
    {
        return 'عنوان الاختبار';
    }

    public function message(object $notifiable): string
    {
        return 'نص الاختبار';
    }
}
