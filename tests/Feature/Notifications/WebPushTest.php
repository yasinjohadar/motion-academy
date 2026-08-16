<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Support\NotificationChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    protected function subscribedUser(): User
    {
        $user = User::factory()->create();

        $user->updatePushSubscription(
            'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkTLtoZM7uWOxDLEuXFVpFqcRe4kNjPZmCTQnPUvKPdWuLQZFmXBvUQ',
            'auth-token-value',
            'aes128gcm',
        );

        return $user;
    }

    public function test_a_user_can_register_a_push_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('admin.notifications.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-value'],
                'content_encoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    public function test_subscribing_requires_the_endpoint_and_keys(): void
    {
        // Must be real 422 JSON, not the HTML redirect that $request->validate() would
        // produce here — bootstrap/app.php only renders JSON exceptions for `api/*`.
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.notifications.push.subscribe'), ['endpoint' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_guests_cannot_register_a_subscription(): void
    {
        // This app redirects unauthenticated requests rather than answering 401,
        // because JSON exception rendering is scoped to `api/*`.
        $this->postJson(route('admin.notifications.push.subscribe'), [
            'endpoint' => 'https://example.com/push',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_user_can_remove_their_push_subscription(): void
    {
        $user = $this->subscribedUser();

        $this->actingAs($user)
            ->deleteJson(route('admin.notifications.push.unsubscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            ])
            ->assertOk()
            ->assertJson(['status' => 'unsubscribed']);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);
    }

    public function test_webpush_channel_is_unavailable_without_a_subscription(): void
    {
        $available = app(NotificationChannelResolver::class)
            ->isAvailable(NotificationChannel::WebPush, User::factory()->create());

        $this->assertFalse($available);
    }

    public function test_webpush_channel_becomes_available_once_subscribed(): void
    {
        $available = app(NotificationChannelResolver::class)
            ->isAvailable(NotificationChannel::WebPush, $this->subscribedUser());

        $this->assertTrue($available);
    }

    public function test_webpush_is_used_when_subscribed_and_enabled(): void
    {
        $user = $this->subscribedUser();

        NotificationPreference::syncForUser($user, [
            NotificationCategory::Backups->value => [
                NotificationChannel::WebPush->value => true,
            ],
        ]);

        $this->assertContains(WebPushChannel::class, (new WebPushTestNotification)->via($user));
    }

    public function test_the_push_message_carries_title_body_and_click_url(): void
    {
        $user = $this->subscribedUser();

        $message = (new WebPushTestNotification)->toWebPush($user)->toArray();

        $this->assertSame('عنوان الاختبار', $message['title']);
        $this->assertSame('نص الاختبار', $message['body']);
        $this->assertSame('https://example.com/target', $message['data']['url']);
        $this->assertSame(NotificationCategory::Backups->value, $message['tag']);
    }

    /**
     * Guards a deployment mistake — push notifications configured but no keys — rather
     * than asserting a fact about the machine running the suite.
     *
     * As written before, this asserted that whoever ran the tests had generated VAPID
     * keys into their own .env. It passed on one developer's machine and failed for
     * every new clone and every CI run, which makes it a report about the environment
     * rather than about the application.
     */
    public function test_vapid_keys_are_complete_when_web_push_is_configured(): void
    {
        $public = config('webpush.vapid.public_key');

        if (blank($public)) {
            $this->markTestSkipped('VAPID keys are not configured; run `php artisan webpush:vapid`.');
        }

        // A half-configured pair is the dangerous state: subscriptions are accepted and
        // every send then fails.
        $this->assertNotEmpty(config('webpush.vapid.private_key'));
        $this->assertNotEmpty(config('webpush.vapid.subject'));
    }
}

class WebPushTestNotification extends BaseNotification
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

    public function actionUrl(object $notifiable): ?string
    {
        return 'https://example.com/target';
    }
}
